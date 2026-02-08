ARG PHP_VERSION="8.4"
FROM php:${PHP_VERSION:+${PHP_VERSION}-}apache

# Update the image and install CRON
RUN apt update \
    && apt upgrade -y \
    && apt install cron -y \
    && apt clean

# Update the CA roots
RUN curl -s -O https://curl.haxx.se/ca/cacert.pem && mv cacert.pem /usr/local/share/ca-certificates \
    && update-ca-certificates

# Install the MySQLi PHP extension
RUN docker-php-ext-install -j12 mysqli

# Copy the custom php.ini file
COPY assets/php.ini /usr/local/etc/php/conf.d/php.ini

# Copy the application files
COPY . /var/www/html

# Add a user for Apache
RUN useradd panopticon

ENV APACHE_RUN_USER="panopticon"
ENV APACHE_RUN_GROUP="panopticon"

# Copy the custom entrypoint file and tell Docker to use it
COPY assets/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["bash", "/usr/local/bin/docker-entrypoint.sh"]
