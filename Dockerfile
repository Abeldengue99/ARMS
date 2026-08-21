FROM php:8.3-fpm-alpine

# Instalar extensões PHP necessárias e nginx
RUN apk add --no-cache \
    nginx \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        intl \
        mbstring \
        opcache \
    && rm -rf /var/cache/apk/*

# Copiar a configuração do nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copiar o código da aplicação
COPY . /app
WORKDIR /app

# Garantir permissões
RUN chown -R www-data:www-data /app

# Criar o script de arranque
RUN echo '#!/bin/sh' > /start.sh && \
    echo 'php-fpm -D' >> /start.sh && \
    echo 'nginx -g "daemon off;"' >> /start.sh && \
    chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
