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
    && mkdir -p /docker-entrypoint-initdb.d \
    && cp /repo/db/init.sql /docker-entrypoint-initdb.d/init.sql \
    && chown -R www-data:www-data /var/www/html \
    && rm -rf /repo

# Persistenter Uploads-Pfad (Docker Volume)
VOLUME /var/www/html/uploads

# Skript hinzufügen, das Umgebungsvariablen setzt
RUN echo '#!/bin/bash' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv DB_USER \"${DB_USER}\"" > /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv DB_PASSWORD \"${DB_PASSWORD}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_HOST \"${SMTP_HOST}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_PORT \"${SMTP_PORT}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_USER \"${SMTP_USER}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_PASSWORD \"${SMTP_PASSWORD}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_FROM \"${SMTP_FROM}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_NAME \"${SMTP_NAME}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv ADMIN_EMAIL \"${ADMIN_EMAIL}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'echo "SetEnv SMTP_ENCRYPTION \"${SMTP_ENCRYPTION}\"" >> /etc/apache2/conf-available/environment.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'a2enconf environment' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec "$@"' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Docker Entrypoint: Setze die Umgebungsvariablen und starte Apache
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# Apache-Standardbefehl
CMD ["apache2-foreground"]
