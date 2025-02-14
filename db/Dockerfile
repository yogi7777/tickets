FROM mariadb:latest

# Git installieren
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

# GitHub-Repository klonen und init.sql in den Initialisierungsordner kopieren
RUN git clone --depth=1 https://github.com/yogi7777/tickets.git /repo \
    && cp /repo/db/init.sql /docker-entrypoint-initdb.d/init.sql \
    && rm -rf /repo

# MariaDB-Standard-Entry-Point
CMD ["docker-entrypoint.sh", "mysqld"]
