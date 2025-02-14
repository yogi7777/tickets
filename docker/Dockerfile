FROM php:8.2-apache

# Systemabhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    git \
    libzip-dev \
    zip \
    unzip \
    gettext-base \
    && docker-php-ext-install zip pdo pdo_mysql

# Apache-Konfiguration aktivieren
RUN a2enmod rewrite
RUN a2enmod env

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Erstelle benötigte Ordner und setze Berechtigungen
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html

# Klone das Repository und kopiere den Quellcode in das Apache-Verzeichnis
RUN git clone --depth=1 https://github.com/yogi7777/tickets.git /repo \
    && cp -r /repo/src/* /var/www/html/ \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /repo

# Persistenter Uploads-Pfad (Docker Volume)
VOLUME /var/www/html/uploads

# Environment variablen in die Apache-Konfiguration setzen
COPY apache/environment.conf /etc/apache2/conf-available/environment.conf

# Docker Entrypoint: Setze die Umgebungsvariablen mit envsubst und starte Apache
CMD envsubst < /etc/apache2/conf-available/environment.conf > /etc/apache2/conf-enabled/environment.conf && apache2-foreground
