#!/usr/bin/env bash
#
# Cloud Agent install script for Krayin CRM.
#
# Runs after the repository is checked out to refresh dependencies and prepare a
# seeded local development database. It is idempotent: dependencies are
# reinstalled, but an already-populated database is migrated in place rather than
# wiped, so re-running never destroys existing data.
#
# System packages (PHP 8.3, Composer, MySQL 8.0, Node) are provided by the base
# environment snapshot, not installed here.
set -euo pipefail

cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "==> Ensuring .env with local development defaults"
if [ ! -f .env ]; then
  cp .env.example .env
fi

php -r '
$file = ".env";
$env = file_get_contents($file);
$values = [
    "APP_ENV"       => "local",
    "APP_DEBUG"     => "true",
    "APP_URL"       => "http://localhost:8000",
    "DB_CONNECTION" => "mysql",
    "DB_HOST"       => "127.0.0.1",
    "DB_PORT"       => "3306",
    "DB_DATABASE"   => "krayin",
    "DB_USERNAME"   => "krayin",
    "DB_PASSWORD"   => "",
    "MAIL_MAILER"   => "log",
];
foreach ($values as $key => $value) {
    $env = preg_replace("/^" . preg_quote($key, "/") . "=.*/m", $key . "=" . $value, $env);
}
file_put_contents($file, $env);
'

echo "==> Installing PHP dependencies"
composer install --no-interaction --prefer-dist

echo "==> Installing JS dependencies and building assets"
npm install
npm run build

echo "==> Starting MySQL and ensuring the dev database/user exist"
sudo service mysql start || true
for _ in $(seq 1 30); do
  if sudo mysqladmin ping >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

sudo mysql <<'SQL'
CREATE DATABASE IF NOT EXISTS krayin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'krayin'@'%' IDENTIFIED WITH caching_sha2_password BY '';
CREATE USER IF NOT EXISTS 'krayin'@'localhost' IDENTIFIED WITH caching_sha2_password BY '';
ALTER USER 'krayin'@'%' IDENTIFIED WITH caching_sha2_password BY '';
ALTER USER 'krayin'@'localhost' IDENTIFIED WITH caching_sha2_password BY '';
GRANT ALL PRIVILEGES ON *.* TO 'krayin'@'%' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'krayin'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;
SQL

echo "==> Generating application key if missing"
grep -q '^APP_KEY=base64:' .env || php artisan key:generate

# Only run the full installer (migrate:fresh + seed) on a brand-new database.
# On an already-installed database, apply any pending migrations in place.
USER_COUNT="$(mysql -ukrayin -h127.0.0.1 -N -e 'SELECT COUNT(*) FROM krayin.users' 2>/dev/null || true)"
if [ -n "${USER_COUNT}" ] && [ "${USER_COUNT}" -ge 1 ] 2>/dev/null; then
  echo "==> Existing installation detected (${USER_COUNT} user(s)); applying pending migrations"
  php artisan migrate --force
  php artisan optimize:clear
else
  echo "==> Fresh database detected; running the Krayin installer"
  php artisan krayin-crm:install --skip-env-check --skip-admin-creation
fi

echo "==> Install complete"
