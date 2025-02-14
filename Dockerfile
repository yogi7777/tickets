FROM php:8.2-apache

# Systemabhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install zip pdo pdo_mysql

# Apache-Konfiguration
RUN a2enmod rewrite

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Erstelle benötigte Ordner und setze Berechtigungen
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html

# Repository von GitHub klonen
RUN git clone --depth=1 https://github.com/yogi7777/tickets.git /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Persistenter Uploads-Pfad (Docker Volume)
VOLUME /var/www/html/uploads

CMD ["apache2-foreground"]
