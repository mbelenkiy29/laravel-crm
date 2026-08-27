#!/bin/sh
set -eu

# Re-apply the MYSQL_USER password on every start so Render's MYSQL_PASSWORD
# env still matches grants (docker-entrypoint init scripts run only once).
sync_app_user() {
    i=0
    while [ "$i" -lt 60 ]; do
        if mysqladmin ping -h 127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent; then
            pass=$(printf '%s' "${MYSQL_PASSWORD}" | sed "s/'/''/g")
            mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" --protocol=tcp -h 127.0.0.1 -e "
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${pass}';
ALTER USER '${MYSQL_USER}'@'%' IDENTIFIED BY '${pass}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
" && return 0
        fi
        i=$((i + 1))
        sleep 2
    done
    echo "krayin-mysql: timed out waiting to sync app user grants" >&2
    return 1
}

sync_app_user &

exec docker-entrypoint.sh "$@"
