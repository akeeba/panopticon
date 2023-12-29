FROM dunglas/frankenphp

# Update the image and install dependencies
RUN apt update \
    && apt upgrade -y \
    && apt install cron libnss3-tools -y \
    && apt clean

# Update the CA roots
RUN curl -s -O https://curl.haxx.se/ca/cacert.pem && mv cacert.pem /usr/local/share/ca-certificates \
    && update-ca-certificates

# Install the MySQLi PHP extension
RUN install-php-extensions \
    bcmath \
    gmp \
    intl \
    mysqli \
    opcache \
    pdo_mysql \
    redis \
    zip

# Copy the custom php.ini file
COPY assets/php.ini /usr/local/etc/php/php.ini

# Copy the application files
COPY . /app/public

VOLUME /app/public

# Add a user for Apache
RUN useradd panopticon

ENV PANOPTICON_DB_HOST="mysql"
ENV PANOPTICON_DB_PREFIX="pnptc_"
ENV MYSQL_DATABASE="panopticon"
ENV MYSQL_USER="panopticon"
ENV MYSQL_PASSWORD="Emx6Rf9mtneXNgpZyehvdm8NUJJMJQA8"
ENV ADMIN_USERNAME="admin"
ENV ADMIN_PASSWORD="admin"
ENV ADMIN_NAME="Super Administrator"
ENV ADMIN_EMAIL="admin@example.com"

# Copy the custom entrypoint file and tell Docker to use it
COPY assets/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

# File permissions
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["bash", "/usr/local/bin/docker-entrypoint.sh"]