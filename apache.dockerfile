# Dockerfile für die PHP/Apache-Umgebung
FROM php:8.2-apache

# System Dependencies installieren
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install zip pdo pdo_mysql

# Apache Konfiguration
RUN a2enmod rewrite

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# PHP Konfiguration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Erstelle benötigte Ordner und setze Berechtigungen
RUN mkdir -p /var/www/html/uploads \
    && mkdir -p /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html