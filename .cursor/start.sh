#!/usr/bin/env bash
#
# Cloud Agent start script for Krayin CRM.
#
# Runs on every boot to bring up the local MySQL service before the agent uses
# the application. It waits for MySQL to accept connections and then returns so
# that the configured terminals (php artisan serve, vite) can start.
set -euo pipefail

echo "==> Starting MySQL"
sudo service mysql start || true

for _ in $(seq 1 30); do
  if sudo mysqladmin ping >/dev/null 2>&1; then
    echo "==> MySQL is ready"
    exit 0
  fi
  sleep 1
done

echo "MySQL did not become ready in time" >&2
exit 1
