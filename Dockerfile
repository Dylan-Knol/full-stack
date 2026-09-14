# FROM php:8.5-apache

# Deze version is geinstalleerd op Flexahosting.nl
FROM php:8.5-apache

# Installeer extra extensies
RUN docker-php-ext-install pdo_mysql mysqli

# Nodig voor Composer
RUN apt-get update 
RUN apt-get install -y unzip libzip-dev
RUN docker-php-ext-install zip

# Installeer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Voeg alias toe voor bash
RUN echo "alias ll='ls -lha --color'" >> /root/.bashrc