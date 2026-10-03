# ---- Stage 1: production dependencies only (composer never reaches the runtime image) ----
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --no-autoloader
COPY app ./app
RUN composer dump-autoload --optimize --no-dev

# ---- Stage 2: runtime ----
FROM php:8.2-apache

# PDO MySQL driver (plus mysqli in case any tooling expects it).
RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite headers

# Point Apache's DocumentRoot at the public/ front-controller directory.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/sites-available/*.conf \
    && sed -ri -e "s!/var/www/!${APACHE_DOCUMENT_ROOT}!g" /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides (front-controller rewrite rules) and make the uploads
# directory inert: no PHP execution and no MIME sniffing, whatever file lands there.
RUN { \
        echo '<Directory /var/www/html/public>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
        echo '<Directory /var/www/html/public/uploads>'; \
        echo '    <IfModule mod_php.c>'; \
        echo '        php_admin_flag engine off'; \
        echo '    </IfModule>'; \
        echo '    <FilesMatch "\.(php|phtml|phar|php[0-9])$">'; \
        echo '        Require all denied'; \
        echo '    </FilesMatch>'; \
        echo '    Header set X-Content-Type-Options "nosniff"'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/ioms.conf \
    && a2enconf ioms

# Do not advertise server/PHP versions; never print errors to clients; harden session cookies.
RUN { \
        echo 'ServerTokens Prod'; \
        echo 'ServerSignature Off'; \
        echo 'TraceEnable Off'; \
    } > /etc/apache2/conf-available/zz-security-hardening.conf \
    && a2enconf zz-security-hardening \
    && { \
        echo 'expose_php = Off'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
        echo 'session.use_strict_mode = 1'; \
        echo 'session.use_only_cookies = 1'; \
        echo 'session.cookie_httponly = 1'; \
        echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/zz-security.ini

WORKDIR /var/www/html

# Application source, then the production-only vendor/ built in stage 1.
COPY . .
COPY --from=vendor /app/vendor ./vendor

# Run as an unprivileged user instead of root. Non-root cannot bind port 80,
# so Apache listens on 8080 and writes its runtime files to dirs we own.
ARG APP_UID=1000
ARG APP_GID=1000
RUN groupadd -g ${APP_GID} appuser \
    && useradd -u ${APP_UID} -g appuser -m -s /usr/sbin/nologin appuser \
    && sed -ri -e 's/Listen 80$/Listen 8080/' /etc/apache2/ports.conf \
    && sed -ri -e 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/*.conf \
    && mkdir -p public/uploads \
    && chown -R appuser:appuser /var/www/html /var/run/apache2 /var/lock/apache2 /var/log/apache2 \
    && chmod -R 755 public/uploads

ENV APACHE_RUN_USER=appuser \
    APACHE_RUN_GROUP=appuser

USER appuser

EXPOSE 8080
