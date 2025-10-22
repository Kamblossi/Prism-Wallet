# Use the php:8.2-fpm-alpine base image
FROM php:8.2-fpm-alpine

# Set working directory to /var/www/html
WORKDIR /var/www/html

# Update packages and install dependencies
RUN apk upgrade --no-cache && \
    apk add --no-cache dumb-init shadow libpng libpng-dev libjpeg-turbo libjpeg-turbo-dev freetype freetype-dev curl autoconf libgomp icu-dev icu-data-full nginx dcron tzdata imagemagick imagemagick-dev libzip-dev postgresql-dev libwebp-dev dos2unix && \
    docker-php-ext-install pdo pdo_pgsql calendar && \
    docker-php-ext-enable pdo pdo_pgsql && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd intl zip && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    apk del .build-deps

# Copy your PHP application files into the container
COPY . .

# Copy debug script and make it executable
COPY debug-startup.sh /usr/local/bin/debug-startup.sh
RUN chmod +x /usr/local/bin/debug-startup.sh

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf
# Place default server into conf.d so it is included by our main nginx.conf
COPY nginx.default.conf /etc/nginx/conf.d/default.conf
# Ensure no stray http.d default from base image remains
RUN rm -f /etc/nginx/http.d/default.conf || true

# Remove nginx conf files from webroot
RUN rm -rf /var/www/html/nginx.conf && \
    rm -rf /var/www/html/nginx.default.conf

# Copy the custom crontab file
COPY cronjobs /etc/cron.d/cronjobs

# Convert the line endings, allow read access to the cron file, and create cron log folder
RUN dos2unix /etc/cron.d/cronjobs && \
    dos2unix /var/www/html/startup.sh && \
    chmod 0644 /etc/cron.d/cronjobs && \
    /usr/bin/crontab /etc/cron.d/cronjobs && \
    mkdir /var/log/cron && \
    chown -R www-data:www-data /var/www/html && \
    chmod +x /var/www/html/startup.sh && \
    # Copy custom php-fpm pool config to ensure we listen on 127.0.0.1:9000
    mkdir -p /usr/local/etc/php-fpm.d && \
    install -m 0644 /var/www/html/docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf && \
    echo 'pm.max_children = 15' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'pm.max_requests = 500' >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/zz-docker.conf

# Expose common ports for PaaS detection
# Render typically provides $PORT (defaults to 10000)
EXPOSE 80 10000

ENTRYPOINT ["dumb-init", "--"]

# Requires docker engine 25+ for the --start-interval flag
HEALTHCHECK --interval=2m --timeout=2s --start-period=20s --start-interval=5s --retries=3 \
    CMD ["curl", "-fsS", "http://127.0.0.1/healthz.php"]

# Start both PHP-FPM, Nginx
CMD ["/var/www/html/startup.sh"]
