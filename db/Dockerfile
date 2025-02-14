# Verwende das offizielle MariaDB-Image als Basis
FROM mariadb:10.6

# Lade das init.sql-File direkt aus dem Repository herunter
RUN apt-get update && apt-get install -y curl \
    && curl -o /docker-entrypoint-initdb.d/init.sql https://raw.githubusercontent.com/yogi7777/tickets/main/init.sql \
    && chmod +r /docker-entrypoint-initdb.d/init.sql \
    && apt-get remove -y curl \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*
