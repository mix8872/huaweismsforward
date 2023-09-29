FROM php:8.1-cli

ENV COMPOSER_ALLOW_SUPERUSER=1
ARG HOST_UID
RUN useradd www -u $HOST_UID -d /var/www
RUN chown -R www:www /var/www

RUN apt-get update && apt-get install -y git-core libxml2-dev libcurl4-openssl-dev && \
  apt-get clean; rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/* && \
  docker-php-ext-install xml curl

WORKDIR /var/www/html

RUN curl -sS https://getcomposer.org/installer | php -- --2 --install-dir=/usr/local/bin --filename=composer

USER www
