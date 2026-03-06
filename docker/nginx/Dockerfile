FROM nginx:1.29.5-alpine

LABEL maintainer="MaisVendas <molavosbdeveloper@gmail.com>"

# Install curl for healthcheck and remove default config
RUN apk add --no-cache curl \
    && rm /etc/nginx/conf.d/default.conf

# Copy custom configs
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -fsS http://localhost/nginx-health || exit 1

EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
