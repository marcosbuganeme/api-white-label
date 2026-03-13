FROM nginx:1.29.6-alpine@sha256:f46cb72c7df02710e693e863a983ac42f6a9579058a59a35f1ae36c9958e4ce0

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
