# Stjernekig production image: Drupal 11 on the official PHP 8.4 Apache image.
# The same image serves the site and runs cron (see docker-compose.yml).
FROM drupal:11-php8.4-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip default-mysql-client; \
    rm -rf /var/lib/apt/lists/*

# Named to sort after the image's own opcache-recommended.ini, which it overrides.
COPY docker/php-drupal.ini /usr/local/etc/php/conf.d/zz-stjernekig.ini

# Replace the image's stock project with ours.
WORKDIR /opt/drupal
RUN rm -rf /opt/drupal/*
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
    && composer clear-cache

COPY web/modules/custom web/modules/custom
COPY web/themes/custom web/themes/custom
COPY config config
COPY settings.php web/sites/default/settings.php

RUN mkdir -p web/sites/default/files /opt/drupal/private /opt/drupal/backups \
    && chown -R www-data:www-data web/sites/default/files /opt/drupal/private /opt/drupal/backups \
    && chmod -R 775 web/sites/default/files /opt/drupal/private

CMD ["apache2-foreground"]
