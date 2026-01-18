FROM php:8.3-apache

# Render sets $PORT; Apache must listen on that port.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
    git \
    unzip \
    libpq-dev \
    default-mysql-client \
    libzip-dev \
  ; \
  docker-php-ext-configure zip; \
  docker-php-ext-install -j"$(nproc)" \
    pdo \
    pdo_mysql \
    pdo_pgsql \
    zip \
  ; \
  a2enmod rewrite headers; \
  rm -rf /var/lib/apt/lists/*

# Point Apache to Laravel's public/ directory
RUN set -eux; \
  sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf; \
  sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf; \
  { \
    echo '<Directory /var/www/html/public>'; \
    echo '  AllowOverride All'; \
    echo '  Require all granted'; \
    echo '</Directory>'; \
  } > /etc/apache2/conf-available/laravel.conf; \
  a2enconf laravel

# Install Composer (no need for separate image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy only composer files first for better Docker layer caching
# IMPORTANT: we run composer with --no-scripts here because artisan/bootstrap
# are not copied yet (Laravel's post-autoload-dump runs artisan package:discover).
COPY composer.json composer.lock ./
RUN set -eux; \
  composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

# Copy application source
COPY . .

# Now that the full app is present, run the Laravel composer scripts safely.
RUN set -eux; \
  php artisan package:discover --ansi || true

# Ensure Laravel storage/cache folders exist and are writable
RUN set -eux; \
  mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache; \
  chown -R www-data:www-data storage bootstrap/cache

COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
