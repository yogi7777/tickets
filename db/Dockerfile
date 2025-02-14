FROM mariadb:latest

# Git installieren und init.sql von GitHub klonen
RUN apt-get update && apt-get install -y git && rm -rf /var/lib/apt/lists/*

RUN git clone --depth=1 https://github.com/yogi7777/tickets.git /repo \
    && cp /repo/db/init.sql /docker-entrypoint-initdb.d/init.sql \
    && rm -rf /repo

CMD ["docker-entrypoint.sh", "mysqld"]
