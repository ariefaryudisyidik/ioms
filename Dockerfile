FROM php:8.2-apache

# System deps + PHP extensions needed by the app (PDO MySQL driver, plus
# mysqli in case any tooling expects it).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libzip-dev \
        unzip \
        git \
    && docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Point Apache's DocumentRoot at the public/ front-controller directory.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides (front-controller rewrite rules).
RUN { \
        echo '<Directory /var/www/html/public>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/ioms.conf \
    && a2enconf ioms

# Composer, for building the vendor/ directory inside the image.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first for better layer caching.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader \
    || echo "composer install failed or offline; expecting a pre-built vendor/ to be copied in below"

# Now copy the full application source (this also brings an existing
# vendor/ from the host if composer install above could not run, e.g. in
# a fully offline build environment).
COPY . .

# Re-run to make sure autoload files are generated against the final source.
RUN composer dump-autoload --optimize --no-dev || true

RUN mkdir -p public/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 public/uploads

EXPOSE 80
