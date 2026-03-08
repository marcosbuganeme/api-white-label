FROM nginx:1.29.5-alpine@sha256:1d13701a5f9f3fb01aaa88cef2344d65b6b5bf6b7d9fa4cf0dca557a8d7702ba

LABEL maintainer="MaisVendas <dev@maisvenda.store>" \
      org.opencontainers.image.title="API MaisVendas - Nginx" \
      org.opencontainers.image.description="Whitelabel SaaS API - Nginx Reverse Proxy" \
      org.opencontainers.image.vendor="MaisVendas" \
      org.opencontainers.image.source="https://github.com/marcosbuganeme/api-maisvendas" \
      org.opencontainers.image.licenses="Proprietary"

# Install curl for healthcheck and remove default config
RUN apk add --no-cache curl \
    && rm /etc/nginx/conf.d/default.conf

# Copy custom configs
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Bake public assets into the image so production deployments always serve
# fresh assets without relying on named volumes that may retain stale data.
COPY public /var/www/html/public

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -fsS http://localhost/nginx-health || exit 1

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
