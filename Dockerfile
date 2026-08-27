# PHP 8.3-FPM + NGINX image for this Krayin 2.2 fork.
# Do not FROM webkul/krayin:2.0.1 (Hub image is not Laravel 12).
FROM php:8.3-fpm-bookworm

ENV TZ=America/New_York \
    APP_TIMEZONE=America/New_York \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

RUN ln -snf /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo "${TZ}" > /etc/timezone

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        nginx \
        supervisor \
        openssl \
        libicu-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8.10 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/krayin.ini /usr/local/etc/php/conf.d/krayin.ini
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/krayin.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN rm -f /etc/nginx/sites-enabled/default \
    && printf '\nclear_env = no\ncatch_workers_output = yes\n' >> /usr/local/etc/php-fpm.d/www.conf \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && composer --version

COPY . /var/www/html

RUN cp -n .env.example .env \
    && php -r "file_put_contents('.env', preg_replace('/^APP_KEY=.*/m', 'APP_KEY=base64:'.base64_encode(random_bytes(32)), file_get_contents('.env')));" \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 80

VOLUME ["/var/www/html/storage"]

CMD ["/usr/local/bin/entrypoint.sh"]
