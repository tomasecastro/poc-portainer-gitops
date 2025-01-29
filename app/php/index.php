<?php
require 'vendor/autoload.php';

use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;
use Prometheus\Counter;
use Prometheus\Gauge;
use Slim\Factory\AppFactory;
use Slim\Middleware\ErrorMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Slim\Routing\RouteCollectorProxy;

// Configurar PSR-17 Response Factory
$responseFactory = new Psr17Factory();
AppFactory::setResponseFactory($responseFactory);

// Crear logger con formato JSON
$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$app = AppFactory::create();

// Middleware
$app->addRoutingMiddleware();
$app->addBodyParsingMiddleware();
$app->add(new ErrorMiddleware($app->getCallableResolver(), $app->getResponseFactory(), true, true, true));

$registry = new CollectorRegistry(new InMemory());

// Definir métricas
$requestCounter = $registry->getOrRegisterCounter('app', 'http_requests_total', 'Total de solicitudes HTTP', ['method', 'route', 'status_code']);
$memoryUsage = $registry->getOrRegisterGauge('app', 'memory_usage_bytes', 'Uso de memoria en bytes');
$cpuUsage = $registry->getOrRegisterGauge('app', 'cpu_usage_percent', 'Uso de CPU en porcentaje');

// Middleware de Logging y métricas
$app->add(function ($request, $handler) use ($logger, $requestCounter, $memoryUsage, $cpuUsage) {
    $startTime = microtime(true);
    $response = $handler->handle($request);
    $endTime = microtime(true);
    
    $requestCounter->inc([
        $request->getMethod(),
        (string)$request->getUri()->getPath(),
        $response->getStatusCode()
    ]);
    
    $memoryUsage->set(memory_get_usage());
    $cpuUsage->set(sys_getloadavg()[0]);
    
    $logData = [
        'method' => $request->getMethod(),
        'url' => (string)$request->getUri(),
        'status' => $response->getStatusCode(),
        'hostname' => gethostname(),
        'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
        'response_time_ms' => ($endTime - $startTime) * 1000
    ];
    $logger->info(json_encode($logData));
    
    return $response;
});

// Definir rutas
$app->get('/info', function ($request, $response) {
    $data = [
        'hostname' => gethostname(),
        'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
        'headers' => $request->getHeaders()
    ];
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'healthy']));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->get('/metrics', function ($request, $response) use ($registry) {
    $renderer = new RenderTextFormat();
    $metrics = $renderer->render($registry->getMetricFamilySamples());
    $response->getBody()->write($metrics);
    return $response->withHeader('Content-Type', RenderTextFormat::MIME_TYPE);
});

// Redirigir "/" a `index.html`
$app->get('/', function ($request, $response) {
    $file = __DIR__ . '/www/index.html';
    if (file_exists($file)) {
        $response->getBody()->write(file_get_contents($file));
        return $response->withHeader('Content-Type', 'text/html');
    }
    return $response->withStatus(404)->write(json_encode(['error' => 'Not Found']));
});

// Iniciar la aplicación
$app->run();