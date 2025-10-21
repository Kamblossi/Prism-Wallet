#!/bin/sh

set -euo pipefail
set -x

echo "==================================="
echo "Prism Wallet Container Starting"
echo "Date: $(date)"
echo "==================================="
echo "Startup script is running..." > /var/log/startup.log
echo "Startup time: $(date)" >> /var/log/startup.log

# Default the PUID and PGID environment variables to 82, otherwise
# set to the user defined ones.
PUID=${PUID:-82}
PGID=${PGID:-82}

# Change the www-data user id and group id to be the user-specified ones
groupmod -o -g "$PGID" www-data || true
usermod -o -u "$PUID" www-data || true
chown -R www-data:www-data /var/www/html || true
chown -R www-data:www-data /tmp || true
chmod -R 770 /tmp || true

## Resolve postgres hostname and add to /etc/hosts for libpq compatibility
## This MUST happen after user setup but BEFORE PHP-FPM starts
## Use DB_HOST env var if provided, otherwise try known service/container names

# Allow runtime override from environment
DB_HOST=${DB_HOST:-}

if [ -n "$DB_HOST" ]; then
  echo "DB_HOST explicitly set to: $DB_HOST"
else
  # prefer service name defined in docker-compose
  DB_HOST=prism-wallet-postgres
fi

# Try to resolve the DB_HOST, retry a few times to allow DNS to settle
POSTGRES_IP=""
for try in 1 2 3 4 5; do
  echo "Attempting to resolve DB host '$DB_HOST' (attempt $try/5)"
  POSTGRES_IP=$(getent hosts "$DB_HOST" 2>/dev/null | awk '{ print $1 }' | head -1 || true)
  if [ -n "$POSTGRES_IP" ]; then
    echo "Resolved $DB_HOST -> $POSTGRES_IP"
    break
  fi
  # also attempt plain 'postgres' as a fallback
  POSTGRES_IP=$(getent hosts postgres 2>/dev/null | awk '{ print $1 }' | head -1 || true)
  if [ -n "$POSTGRES_IP" ]; then
    echo "Resolved fallback 'postgres' -> $POSTGRES_IP"
    DB_HOST=postgres
    break
  fi
  sleep 1
done

if [ -n "$POSTGRES_IP" ]; then
  echo "Adding postgres hostnames ($POSTGRES_IP) to /etc/hosts for PHP PDO compatibility"
  # Avoid duplicating entries (match IP followed by hostname)
  grep -q -E "^${POSTGRES_IP}[[:space:]]+postgres([[:space:]]|$)" /etc/hosts 2>/dev/null || echo "$POSTGRES_IP postgres" >> /etc/hosts
  grep -q -E "^${POSTGRES_IP}[[:space:]]+prism-wallet-postgres([[:space:]]|$)" /etc/hosts 2>/dev/null || echo "$POSTGRES_IP prism-wallet-postgres" >> /etc/hosts

  # Also save the IP to a file that PHP can read
  echo "$POSTGRES_IP" > /tmp/postgres_ip.txt
  chown www-data:www-data /tmp/postgres_ip.txt || true
  echo "Saved postgres IP to /tmp/postgres_ip.txt"
else
  echo "Warning: Could not resolve postgres hostname after retries. DB_HOST is set to '$DB_HOST'"
  echo "If you rely on DNS-based resolution, ensure the DB service is reachable or set DB_HOST env var to the IP or host name."
fi

# PIDs we’ll track
PHP_FPM_PID=
NGINX_PID=
CROND_PID=
shutdown_in_progress=0

shutdown_once() {
  exit_signal=$?
  kill_signal=$(kill -l "$exit_signal" 2>/dev/null || echo "$exit_signal")

  [ "$shutdown_in_progress" -eq 1 ] && return 0
  shutdown_in_progress=1

  echo "Got signal: $kill_signal - Shutting down gracefully... "
  # nginx wants QUIT for graceful
  nginx -s quit || true
  # php-fpm graceful quit as well
  [ -n "${PHP_FPM_PID}" ] && kill -QUIT "${PHP_FPM_PID}" 2>/dev/null || true
  # cron can just get TERM
  [ -n "${CROND_PID}" ] && kill -TERM "${CROND_PID}" 2>/dev/null || true
  echo "Graceful shutdown complete."
}

# Handle all common stop signals
trap 'shutdown_once' SIGTERM SIGINT SIGQUIT

# Ensure PHP dependencies are installed if Composer is available
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
  if command -v composer >/dev/null 2>&1; then
    echo "Installing PHP dependencies with Composer..." | tee -a /var/log/startup.log
    (cd /var/www/html && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction) || true
  else
    echo "Composer not found; skipping vendor install. Ensure vendor/ is baked into the image." | tee -a /var/log/startup.log
  fi
fi

# Test PHP-FPM configuration before launching the service so we fail fast with a clear message
echo "==================================="
echo "Testing PHP-FPM Configuration"
echo "==================================="
echo "Checking /usr/local/etc/php-fpm.d/www.conf..."
if [ -f /usr/local/etc/php-fpm.d/www.conf ]; then
  echo "✓ www.conf exists"
  grep "listen" /usr/local/etc/php-fpm.d/www.conf || echo "Warning: no listen directive found"
else
  echo "✗ WARNING: www.conf not found!"
fi

if php-fpm -t 2>&1 | tee /var/log/phpfpm-config-check.log; then
  echo "✓ php-fpm configuration test PASSED"
else
  echo "✗ WARNING: php-fpm configuration test FAILED"
  echo "See detailed output below:"
  cat /var/log/phpfpm-config-check.log >&2
  echo "Continuing anyway (non-fatal)..."
fi
echo ""

# Start both PHP-FPM and Nginx
echo "==================================="
echo "Starting PHP-FPM"
echo "==================================="
php-fpm -F &
PHP_FPM_PID=$!
echo "✓ PHP-FPM started with PID: $PHP_FPM_PID"
echo ""

echo "==================================="
echo "Waiting for PHP-FPM to Listen"
echo "==================================="
if command -v php >/dev/null 2>&1; then
  for i in $(seq 1 20); do
    if php -r '$s=@fsockopen("127.0.0.1",9000,$e,$str,1); if ($s) { fclose($s); exit(0);} exit(1);'; then
      echo "✓ PHP-FPM is ready and accepting connections on 127.0.0.1:9000"
      break
    fi
    if [ $i -eq 20 ]; then
      echo "✗ WARNING: PHP-FPM did not respond after 20 attempts (10 seconds)"
      echo "Checking if process is still running..."
      if kill -0 $PHP_FPM_PID 2>/dev/null; then
        echo "  PHP-FPM process is alive but not responding on port 9000"
      else
        echo "  ✗ PHP-FPM process has died! Check logs above."
      fi
      echo "Continuing anyway..."
    fi
    echo "  Waiting for php-fpm... (attempt $i/20)"
    sleep 0.5
  done
else
  echo "⚠ php CLI not found — skipping active wait for php-fpm"
  echo "  Waiting 2 seconds for PHP-FPM to start..."
  sleep 2
fi
echo ""

echo "==================================="
echo "Starting Crond"
echo "==================================="
crond -f &
CROND_PID=$!
echo "✓ Crond started with PID: $CROND_PID"
echo ""

echo "==================================="
echo "Starting Nginx"
echo "==================================="
# Final nginx config check before starting
echo "Running final nginx configuration test..."
if nginx -t 2>&1; then
  echo "✓ Nginx configuration is valid"
else
  echo "✗ ERROR: Nginx configuration test failed!"
  echo "This will likely cause Nginx to fail to start."
fi

nginx -g 'daemon off;' &
NGINX_PID=$!
echo "✓ Nginx started with PID: $NGINX_PID"
echo ""

touch ~/startup.txt

# Wait one second before running scripts
sleep 1

echo "==================================="
echo "Running Initialization Tasks"
echo "==================================="

# Perform database migrations for PostgreSQL
echo "Running database migrations..."
if /usr/local/bin/php /var/www/html/endpoints/db/migrate.php 2>&1 | tee -a /var/log/startup.log; then
  echo "✓ Database migrations completed"
else
  echo "⚠ Database migration returned non-zero exit code (may be expected if DB not ready)"
fi
echo ""

mkdir -p /var/www/html/images/uploads/logos/avatars

# Change permissions on the logos directory
chmod -R 755 /var/www/html/images/uploads/logos
chown -R www-data:www-data /var/www/html/images/uploads/logos

# Remove crontab for the user (ignore failure if none exists)
crontab -d -u root 2>/dev/null || true

# Run updatenextpayment.php and wait for it to finish
# Temporarily disabled due to missing cycles table
# /usr/local/bin/php /var/www/html/endpoints/cronjobs/updatenextpayment.php

# Run updateexchange.php
# Temporarily disabled since there are no users yet
# /usr/local/bin/php /var/www/html/endpoints/cronjobs/updateexchange.php

# Run checkforupdates.php (non-fatal)
echo "Running checkforupdates.php..."
/usr/local/bin/php /var/www/html/endpoints/cronjobs/checkforupdates.php || echo "⚠ checkforupdates.php failed (non-fatal)" | tee -a /var/log/startup.log

echo ""
echo "==================================="
echo "Startup Complete - Services Running"
echo "==================================="
echo "PHP-FPM PID: $PHP_FPM_PID"
echo "Nginx PID:   $NGINX_PID"
echo "Crond PID:   $CROND_PID"
echo "==================================="
echo "Container is ready to serve traffic"
echo "Logs: /var/log/startup.log"
echo "==================================="
echo ""

# Essentially wait until all child processes exit
wait
