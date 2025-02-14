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

#Variable definieren
ENV DB_USER=${DB_USER}
ENV DB_PASSWORD=${DB_PASSWORD}
ENV SMTP_HOST=${SMTP_HOST}
ENV SMTP_PORT=${SMTP_PORT}
ENV SMTP_USER=${SMTP_USER}
ENV SMTP_PASSWORD=${SMTP_PASSWORD}
ENV SMTP_FROM=${SMTP_FROM}
ENV SMTP_NAME=${SMTP_NAME}
ENV ADMIN_EMAIL=${ADMIN_EMAIL}
ENV SMTP_ENCRYPTION=${SMTP_ENCRYPTION}

# Aktiviere alle Umgebungsvariablen für PHP
RUN echo "SetEnv DB_USER \${DB_USER}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv DB_PASSWORD \${DB_PASSWORD}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_HOST \${SMTP_HOST}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_PORT \${SMTP_PORT}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_USER \${SMTP_USER}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_PASSWORD \${SMTP_PASSWORD}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_FROM \${SMTP_FROM}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_NAME \${SMTP_NAME}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv ADMIN_EMAIL \${ADMIN_EMAIL}" >> /etc/apache2/conf-available/environment.conf && \
    echo "SetEnv SMTP_ENCRYPTION \${SMTP_ENCRYPTION}" >> /etc/apache2/conf-available/environment.conf && \
    a2enconf environment

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

CMD ["apache2-foreground"]
