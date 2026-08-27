FROM mysql:8.0.40

COPY charset.cnf /etc/mysql/conf.d/charset.cnf
COPY entrypoint.sh /usr/local/bin/krayin-mysql-entrypoint.sh

RUN chmod +x /usr/local/bin/krayin-mysql-entrypoint.sh

ENV MYSQL_ROOT_HOST=%

ENTRYPOINT ["/usr/local/bin/krayin-mysql-entrypoint.sh"]
CMD ["mysqld"]
