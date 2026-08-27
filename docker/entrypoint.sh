#!/bin/sh
set -eu

APP_DIR=/var/www/html
cd "$APP_DIR"

PORT="${PORT:-80}"
sed -i "s/listen 80 /listen ${PORT} /g" /etc/nginx/conf.d/default.conf

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
export MAIL_HOST="${MAIL_HOST:-mailhog}"
export MAIL_PORT="${MAIL_PORT:-1025}"
export MAIL_USERNAME="${MAIL_USERNAME:-}"
export MAIL_PASSWORD="${MAIL_PASSWORD:-}"
export MAIL_ENCRYPTION="${MAIL_ENCRYPTION:-}"
export MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-laravel@krayincrm.com}"

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

wait_for_mysql() {
    i=0
    while [ "$i" -lt 90 ]; do
        if php -r '
            $host = getenv("DB_HOST");
            $port = getenv("DB_PORT") ?: "3306";
            $db = getenv("DB_DATABASE");
            $user = getenv("DB_USERNAME");
            $pass = getenv("DB_PASSWORD");
            try {
                new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass);
                exit(0);
            } catch (Throwable $e) {
                fwrite(STDERR, $e->getMessage()."\n");
                exit(1);
            }
        ' 2>/tmp/mysql-wait.err; then
            return 0
        fi
        i=$((i + 1))
        echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT} (${i}/90)..."
        sleep 2
    done
    echo "MySQL did not become ready in time." >&2
    if [ -f /tmp/mysql-wait.err ]; then
        sed 's/using password: .*/using password: YES/' /tmp/mysql-wait.err >&2 || true
    fi
    return 1
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

wait_for_mysql

if ! already_installed; then
    echo "First boot: equivalent of krayin-crm:install --skip-env-check --skip-admin-creation"
    echo "Using migrate:fresh --force (production has no TTY) and process-env DB_PASSWORD (not the installer .env parser)."
    php artisan migrate:fresh --force --no-interaction
    php artisan db:seed --force --no-interaction
    php artisan vendor:publish --provider="Webkul\\Core\\Providers\\CoreServiceProvider" --force --no-interaction
    php artisan storage:link --force --no-interaction
    php artisan optimize:clear --no-interaction
    echo "Krayin is successfully installed" > storage/installed
else
    echo "Krayin already installed; skipping installer."
fi

php artisan storage:link --force >/dev/null 2>&1 || true

chown -R www-data:www-data storage bootstrap/cache

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/krayin.conf
