FROM dimaip/docker-neos-alpine:php74
ENV PHP_TIMEZONE=Europe/Moscow
WORKDIR /data/www/Web
USER root
# COPY --chown=80:80 composer.json /data/www-provisioned/composer.json
RUN mkdir -p /data/www/Web
COPY --chown=80:80 ./ /data/www/Web/
RUN cd /data/www/Web && sudo chown 80:80 -R . && composer install
# RUN vendor/bin/phpunit tests
