#!/bin/sh
set -eu

APP_DIR=/var/www/html
cd "$APP_DIR"

PORT="${PORT:-80}"
sed -i "s/listen 0.0.0.0:80 /listen 0.0.0.0:${PORT} /g" /etc/nginx/conf.d/default.conf

export TZ="${TZ:-America/New_York}"
export APP_TIMEZONE="${APP_TIMEZONE:-America/New_York}"
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export MAIL_MAILER="${MAIL_MAILER:-log}"
export APP_URL="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost}}"
export APP_NAME="${APP_NAME:-Krayin CRM}"
export APP_LOCALE="${APP_LOCALE:-en}"
export APP_CURRENCY="${APP_CURRENCY:-USD}"
export DB_CONNECTION="${DB_CONNECTION:-mysql}"
export DB_HOST="${DB_HOST:-mysql}"
export DB_PORT="${DB_PORT:-3306}"
export DB_DATABASE="${DB_DATABASE:-krayin}"
export DB_USERNAME="${DB_USERNAME:-krayin}"
export DB_PASSWORD="${DB_PASSWORD:-}"
export DB_PREFIX="${DB_PREFIX:-}"
export MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-}"
export MAIL_HOST="${MAIL_HOST:-mailhog}"
export MAIL_PORT="${MAIL_PORT:-1025}"
export MAIL_USERNAME="${MAIL_USERNAME:-}"
export MAIL_PASSWORD="${MAIL_PASSWORD:-}"
export MAIL_ENCRYPTION="${MAIL_ENCRYPTION:-}"
export MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-laravel@krayincrm.com}"
export ADMIN_EMAIL="${ADMIN_EMAIL:-}"
export ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/framework/testing \
    storage/logs \
    bootstrap/cache

KEY_FILE=storage/app/.app_key
if [ -f "$KEY_FILE" ] && [ -s "$KEY_FILE" ]; then
    APP_KEY="$(cat "$KEY_FILE")"
    export APP_KEY
elif [ -z "${APP_KEY:-}" ]; then
    APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
    export APP_KEY
fi

case "$APP_KEY" in
    base64:*) ;;
    *)
        APP_KEY="base64:${APP_KEY}"
        export APP_KEY
        ;;
esac

mkdir -p storage/app
printf '%s' "$APP_KEY" > "$KEY_FILE"

# Quote values so Render generateValue passwords (base64, often containing "=")
# survive phpdotenv. krayin-crm:install's getEnvAtRuntime() explodes on every
# "=" and would truncate those passwords — do not use it for first boot.
php -r '
$keys = [
    "APP_NAME", "APP_ENV", "APP_KEY", "APP_DEBUG", "APP_URL",
    "APP_TIMEZONE", "APP_LOCALE", "APP_CURRENCY",
    "DB_CONNECTION", "DB_HOST", "DB_PORT", "DB_DATABASE",
    "DB_USERNAME", "DB_PASSWORD", "DB_PREFIX",
    "MAIL_MAILER", "MAIL_HOST", "MAIL_PORT", "MAIL_USERNAME",
    "MAIL_PASSWORD", "MAIL_ENCRYPTION", "MAIL_FROM_ADDRESS", "MAIL_FROM_NAME",
    "ADMIN_EMAIL", "ADMIN_PASSWORD",
];
$defaults = [
    "MAIL_FROM_NAME" => getenv("APP_NAME") ?: "Krayin CRM",
];
$out = "LOG_CHANNEL=stack\nLOG_LEVEL=debug\nBROADCAST_DRIVER=log\nCACHE_DRIVER=file\nQUEUE_CONNECTION=sync\nSESSION_DRIVER=file\nSESSION_LIFETIME=120\n";
foreach ($keys as $key) {
    $value = getenv($key);
    if ($value === false || $value === "") {
        $value = $defaults[$key] ?? "";
    }
    $out .= $key."=".json_encode($value, JSON_UNESCAPED_SLASHES)."\n";
}
file_put_contents(".env", $out);
'

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

wait_for_mysql_as() {
    role="$1"
    max="${2:-90}"
    i=0
    while [ "$i" -lt "$max" ]; do
        if WAIT_MYSQL_ROLE="$role" php -r '
            $role = getenv("WAIT_MYSQL_ROLE") ?: "app";
            $host = getenv("DB_HOST");
            $port = getenv("DB_PORT") ?: "3306";
            $db = getenv("DB_DATABASE");
            if ($role === "root") {
                $user = "root";
                $pass = getenv("MYSQL_ROOT_PASSWORD") ?: "";
                $dsn = "mysql:host={$host};port={$port}";
            } else {
                $user = getenv("DB_USERNAME");
                $pass = getenv("DB_PASSWORD");
                $dsn = "mysql:host={$host};port={$port};dbname={$db}";
            }
            try {
                new PDO($dsn, $user, $pass);
                exit(0);
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage()."\n");
                exit(1);
            }
        ' 2>/tmp/mysql-wait.err; then
            return 0
        fi
        i=$((i + 1))
        echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT} as ${role} (${i}/${max})..."
        sleep 2
    done
    echo "MySQL did not become ready as ${role} in time." >&2
    if [ -f /tmp/mysql-wait.err ]; then
        sed 's/using password: .*/using password: YES/' /tmp/mysql-wait.err >&2 || true
    fi
    return 1
}

sync_mysql_grants() {
    if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
        echo "MYSQL_ROOT_PASSWORD not set; skipping grant sync."
        return 0
    fi
    php <<'PHP'
<?php
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '3306';
$db = getenv('DB_DATABASE') ?: 'krayin';
$user = getenv('DB_USERNAME') ?: 'krayin';
$pass = getenv('DB_PASSWORD') ?: '';
$root = getenv('MYSQL_ROOT_PASSWORD') ?: '';
try {
    $pdo = new PDO("mysql:host={$host};port={$port}", 'root', $root, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $dbIdent = str_replace('`', '``', $db);
    $userIdent = str_replace("'", "''", $user);
    $passSql = $pdo->quote($pass);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbIdent}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("CREATE USER IF NOT EXISTS '{$userIdent}'@'%' IDENTIFIED BY {$passSql}");
    $pdo->exec("ALTER USER '{$userIdent}'@'%' IDENTIFIED BY {$passSql}");
    $pdo->exec("GRANT ALL PRIVILEGES ON `{$dbIdent}`.* TO '{$userIdent}'@'%'");
    $pdo->exec('FLUSH PRIVILEGES');
    fwrite(STDOUT, "Synced MySQL grants for {$user}@%\n");
} catch (Throwable $e) {
    fwrite(STDERR, 'Grant sync failed: '.$e->getMessage()."\n");
    exit(1);
}
PHP
}

already_installed() {
    php -r '
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: "3306";
        $db = getenv("DB_DATABASE");
        $user = getenv("DB_USERNAME");
        $pass = getenv("DB_PASSWORD");
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
            $exists = $pdo->query("SHOW TABLES LIKE \"users\"")->fetch();
            if (! $exists) {
                exit(1);
            }
            $count = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            exit($count > 0 ? 0 : 1);
        } catch (Throwable $e) {
            exit(1);
        }
    '
}

# Bind PORT immediately so Render health checks pass during first-boot.
term() {
    if [ -n "${SUPERVISOR_PID:-}" ]; then
        kill -TERM "$SUPERVISOR_PID" 2>/dev/null || true
        wait "$SUPERVISOR_PID" || true
    fi
    exit 0
}
trap term TERM INT
/usr/bin/supervisord -c /etc/supervisor/conf.d/krayin.conf &
SUPERVISOR_PID=$!

if [ -n "${MYSQL_ROOT_PASSWORD:-}" ]; then
    if wait_for_mysql_as root 15; then
        sync_mysql_grants || echo "Grant sync failed; continuing as app user."
    else
        echo "Root MySQL login did not succeed; continuing as app user."
    fi
fi

wait_for_mysql_as app

if ! already_installed; then
    echo "First boot: migrate:fresh --force, db:seed --force, then krayin:rotate-admin"
    echo "Using process-env DB_PASSWORD (not the installer .env parser). Default installer admin is disabled when ADMIN_PASSWORD is set."
    php artisan migrate:fresh --force --no-interaction
    php artisan db:seed --force --no-interaction
    php artisan vendor:publish --provider="Webkul\\Core\\Providers\\CoreServiceProvider" --force --no-interaction
    php artisan storage:link --force --no-interaction
    php artisan optimize:clear --no-interaction
    echo "Krayin is successfully installed" > storage/installed
else
    echo "Krayin already installed; skipping installer."
fi

php artisan krayin:rotate-admin --no-interaction

php artisan storage:link --force >/dev/null 2>&1 || true

chown -R www-data:www-data storage bootstrap/cache

wait "$SUPERVISOR_PID"

