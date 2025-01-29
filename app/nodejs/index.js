// index.js
const express = require("express");
const os = require("os");
const promClient = require("prom-client");
const path = require("path");
const pino = require("pino");

const app = express();
const PORT = process.env.APP_PORT || 3000;
const logger = pino();

// Collect default Prometheus metrics
promClient.collectDefaultMetrics();

// Middleware for logging
app.use((req, res, next) => {
    res.on("finish", () => {
        logger.info({
            method: req.method,
            url: req.url,
            status: res.statusCode,
            hostname: os.hostname(),
            ip: req.ip
        });
    });
    next();
});

// Serve static files from /www
app.use("/",express.static(path.join(__dirname, "www")));

// Root endpoint (whoami)
app.get("/info", (req, res) => {
    res.json({
        hostname: os.hostname(),
        ip: req.ip,
        headers: req.headers,
    }); 
});

// Health check endpoint
app.get("/health", (req, res) => res.json({ status: "healthy" }));

// Prometheus metrics endpoint
app.get("/metrics", async (req, res) => {
    res.set("Content-Type", promClient.register.contentType);
    res.end(await promClient.register.metrics());
});

// Start server
app.listen(PORT, () => logger.info({ message: `Server running on port ${PORT}` }));
