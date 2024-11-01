# Use the official PHP-FPM image as the base
FROM php:8.1-fpm

# Install Nginx, Supervisor, and necessary dependencies
RUN apt-get update && \
    apt-get install -y \
        nginx \
        supervisor \
        libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PDO and PDO_SQLITE extensions
RUN docker-php-ext-install pdo pdo_sqlite

# Remove default Nginx site configuration
RUN rm /etc/nginx/sites-enabled/default

# Copy Nginx configuration
COPY ./nginx.conf /etc/nginx/conf.d/default.conf

# Copy PHP-FPM configuration
COPY ./php-fpm.conf /usr/local/etc/php-fpm.d/zzz-custom.conf

# Copy Supervisor configuration
COPY ./supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy your website files to the web root
COPY ./mythwar.thunderized.org /var/www/html

# Set ownership and permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80

# Start Supervisor to manage Nginx and PHP-FPM
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
