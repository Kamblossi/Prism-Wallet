#!/bin/bash
# Debug script to diagnose startup issues
# Run this inside the container or locally to check configuration

echo "==================================="
echo "Prism Wallet Startup Diagnostics"
echo "==================================="
echo ""

echo "1. PHP-FPM Configuration Test:"
echo "-----------------------------------"
php-fpm -t 2>&1
echo ""

echo "2. PHP-FPM Pool Configuration:"
echo "-----------------------------------"
cat /usr/local/etc/php-fpm.d/www.conf 2>/dev/null || echo "www.conf not found"
echo ""

echo "3. Nginx Configuration Test:"
echo "-----------------------------------"
nginx -t 2>&1
echo ""

echo "4. Nginx FastCGI Pass Configuration:"
echo "-----------------------------------"
echo "From main nginx.conf:"
grep -n fastcgi_pass /etc/nginx/nginx.conf || echo "  (none)"
echo ""
echo "From /etc/nginx/conf.d/default.conf:"
grep -n fastcgi_pass /etc/nginx/conf.d/default.conf 2>/dev/null || echo "  (file not found)"
echo ""
echo "From /etc/nginx/http.d/default.conf:"
grep -n fastcgi_pass /etc/nginx/http.d/default.conf 2>/dev/null || echo "  (file not found)"
echo ""

echo "5. Environment Variables:"
echo "-----------------------------------"
env | grep -E "(DB_|AUTH_|APP_)" | sort
echo ""

echo "6. Network Listening Ports (if container running):"
echo "-----------------------------------"
if command -v netstat >/dev/null 2>&1; then
    netstat -tlnp 2>/dev/null || ss -tlnp 2>/dev/null || echo "netstat/ss not available"
else
    echo "netstat not available in this container"
fi
echo ""

echo "7. Running Processes (if container running):"
echo "-----------------------------------"
ps aux 2>/dev/null | grep -E "(nginx|php-fpm|crond)" | grep -v grep || echo "No processes found"
echo ""

echo "8. PHP Version and Extensions:"
echo "-----------------------------------"
php -v
echo ""
php -m | grep -E "(pdo|pgsql|gd|imagick|calendar)" || echo "Extensions not loaded"
echo ""

echo "9. File Permissions Check:"
echo "-----------------------------------"
ls -la /var/www/html/ | head -20
echo ""

echo "10. Startup Log (if exists):"
echo "-----------------------------------"
cat /var/log/startup.log 2>/dev/null || echo "No startup log yet"
echo ""

echo "11. PHP-FPM Config Check Log:"
echo "-----------------------------------"
cat /var/log/phpfpm-config-check.log 2>/dev/null || echo "No php-fpm config check log"
echo ""

echo "12. Test PHP-FPM Socket Connection:"
echo "-----------------------------------"
if command -v php >/dev/null 2>&1; then
    if php -r '$s=@fsockopen("127.0.0.1",9000,$e,$str,1); if ($s) { fclose($s); echo "✓ PHP-FPM is reachable on 127.0.0.1:9000\n"; exit(0);} echo "✗ Cannot connect to PHP-FPM on 127.0.0.1:9000\nError: $e - $str\n"; exit(1);'; then
        echo ""
    else
        echo ""
    fi
else
    echo "PHP CLI not available"
fi
echo ""

echo "==================================="
echo "Diagnostics Complete"
echo "==================================="
