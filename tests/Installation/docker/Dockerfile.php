# Bare Apache 2.4 + PHP-FPM host for the new-installation browser test.
#
# The image contains NO Panopticon code, Composer, config.php or PANOPTICON_* variables: the test unzips the release
# ZIP into /var/www/html and walks the setup wizard like a brand-new user would. The build context is
# tests/Installation/docker; the repository's assets/php.ini arrives through the named context "assets"
# (see compose.yml).
ARG PHP_VERSION="8.3"
FROM php:${PHP_VERSION}-fpm

RUN apt-get update \
	&& apt-get install -y --no-install-recommends apache2 unzip libzip-dev \
	&& rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j"$(nproc)" mysqli pdo_mysql zip

# OPcache: PHP 8.5 has it built in and enabled; earlier versions ship it disabled; assets/php.ini keeps it commented out.
RUN php -m | grep -qi 'Zend OPcache' || docker-php-ext-install opcache

COPY --from=assets php.ini /usr/local/etc/php/conf.d/php.ini
COPY zz-installtest.ini /usr/local/etc/php/conf.d/zz-installtest.ini

RUN a2enmod proxy proxy_fcgi rewrite setenvif \
	&& a2dissite 000-default
COPY apache-vhost.conf /etc/apache2/sites-available/installtest.conf
RUN a2ensite installtest

COPY lab-reset.php /lab/lab-reset.php
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["bash", "/usr/local/bin/entrypoint.sh"]
