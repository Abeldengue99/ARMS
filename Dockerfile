FROM php:8.3.36-fpm-alpine

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
COPY nginx-maintenance.conf /etc/nginx/arms-maintenance.conf
COPY docker/start.sh /start.sh

# Copiar o código da aplicação
COPY . /app
WORKDIR /app

# Garantir permissões
RUN chown -R www-data:www-data /app && \
    chmod +x /start.sh

EXPOSE 80

CMD ["/start.sh"]
