FROM php:8.3-apache

# Installer les dépendances système et extensions PHP
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends \
  git \
  unzip \
  libpq-dev \
  libzip-dev \
  libonig-dev \
  libicu-dev \
  ; \
  # Installation des extensions PHP requises
  docker-php-ext-install -j"$(nproc)" \
  pdo_mysql \
  zip \
  opcache \
  intl \
  bcmath \
  ; \
  # Installation extension Redis via PECL
  pecl install redis; \
  docker-php-ext-enable redis; \
  # Nettoyage
  apt-get clean; \
  rm -rf /var/lib/apt/lists/*

# Configuration Apache pour Laravel
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Activer le mod_rewrite
RUN a2enmod rewrite headers

# Configuration PHP Production
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copier Composer depuis l'image officielle
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Étape 1: Copier seulement les fichiers de dépendances (cache docker layer)
COPY composer.json composer.lock ./

# Étape 2: Installer les dépendances (sans scripts pour l'instant)
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --no-scripts

# Étape 3: Copier le code source de l'application
COPY . .

# Étape 4: Assurer les permissions correctes
RUN chown -R www-data:www-data /var/www/html \
  && chmod -R 775 /var/www/html/storage \
  && chmod -R 775 /var/www/html/bootstrap/cache

# Étape 5: Post-install scripts Laravel (now that code is present)
RUN php artisan package:discover --ansi || true

# Entrypoint script
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080
