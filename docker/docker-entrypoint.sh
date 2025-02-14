#!/bin/bash

# Setze die Umgebungsvariablen in die Apache-Konfigurationsdatei
echo "SetEnv DB_USER ${DB_USER}" > /etc/apache2/conf-available/environment.conf
echo "SetEnv DB_PASSWORD ${DB_PASSWORD}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_HOST ${SMTP_HOST}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_PORT ${SMTP_PORT}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_USER ${SMTP_USER}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_PASSWORD ${SMTP_PASSWORD}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_FROM ${SMTP_FROM}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_NAME ${SMTP_NAME}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv ADMIN_EMAIL ${ADMIN_EMAIL}" >> /etc/apache2/conf-available/environment.conf
echo "SetEnv SMTP_ENCRYPTION ${SMTP_ENCRYPTION}" >> /etc/apache2/conf-available/environment.conf

# Aktiviere die Apache-Konfiguration
a2enconf environment

# Starte Apache im Vordergrund
exec "$@"
