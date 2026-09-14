FROM php:8.3-apache

RUN apt-get update && apt-get install -y unzip zip msmtp msmtp-mta \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/msmtprc /etc/msmtprc
RUN chmod 644 /etc/msmtprc \
    && cp /etc/msmtprc /var/www/.msmtprc \
    && chown www-data:www-data /var/www/.msmtprc \
    && chmod 600 /var/www/.msmtprc

WORKDIR /var/www/html
