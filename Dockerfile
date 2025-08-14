FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nginx \
    supervisor

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (for better caching)
COPY composer.json composer.lock ./

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Create nginx config
RUN echo 'server { \
    listen 8080; \
    root /var/www/html/public; \
    index index.php; \
    location / { \
        try_files $uri $uri/ /index.php?$query_string; \
    } \
    location ~ \.php$ { \
        fastcgi_pass 127.0.0.1:9000; \
        fastcgi_index index.php; \
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; \
        include fastcgi_params; \
    } \
}' > /etc/nginx/sites-available/default

# Create supervisor config
RUN echo '[supervisord] \
nodaemon=true \
user=root \
logfile=/var/log/supervisor/supervisord.log \
pidfile=/var/run/supervisord.pid \
\
[program:php-fpm] \
command=php-fpm \
user=root \
autostart=true \
autorestart=true \
\
[program:nginx] \
command=nginx -g "daemon off;" \
user=root \
autostart=true \
autorestart=true' > /etc/supervisor/conf.d/supervisord.conf

# Generate application key and run post-install scripts
RUN php artisan key:generate --no-interaction || true
RUN composer run-script --no-dev post-autoload-dump || true

# Expose port
EXPOSE 8080

# Start supervisor
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
