FROM dimaip/docker-neos-alpine:latest
ENV PHP_TIMEZONE=Europe/Moscow
WORKDIR /data/www/Web
USER root
# COPY --chown=80:80 composer.json /data/www-provisioned/composer.json
RUN mkdir -p /data/www/Web
COPY --chown=80:80 ./ /data/www/Web/
RUN cd /data/www/Web && sudo chown 80:80 -R . && composer install && git clone https://github.com/dimaip/bible-translations bible
