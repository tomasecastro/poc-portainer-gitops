# Dockerfile for Node.js service

# Hadolint directives for best practices
# hadolint ignore=DL3008,DL3018

# Use official Node.js LTS image as a base for building
FROM node:lts-alpine AS build

# Install tini for graceful shutdown

# Set working directory and create it if necessary
RUN mkdir -p /app /app/www && chown node:node /app /app/www
WORKDIR /app

# Switch to a non-root user provided by the Node.js image
USER node

# Copy package.json and package-lock.json and install dependencies
COPY --chown=node:node package.json package-lock.json ./
RUN npm ci --only=production && npm cache clean --force

# Copy application source code
COPY --chown=node:node . .

# Final stage for production
FROM node:20.18.0-alpine

# Install tini for graceful shutdown
RUN apk update && apk add --no-cache tini

# Set working directory and create necessary directories
WORKDIR /app
RUN mkdir -p /app /app/www && chown node:node /app /app/www

# Switch to non-root user
USER node

# Copy built application from previous stage
COPY --from=build /app /app

# Expose the service port
ENV APP_PORT=3000
EXPOSE ${APP_PORT}

# Serve static files from the 'www' directory

# Health check endpoint
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http:#localhost:${APP_PORT}/health || exit 1

# Set tini as entrypoint for better process handling
ENTRYPOINT ["/bin/tini", "--"]

CMD ["node", "index.js"]
