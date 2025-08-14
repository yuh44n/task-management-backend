FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

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

# Configure Apache DocumentRoot to point to Laravel's public directory
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Create Apache .htaccess for Laravel (if not exists)
RUN echo 'Options -MultiViews -Indexes\nRewriteEngine On\n\n# Handle Authorization Header\nRewriteCond %{HTTP:Authorization} .\nRewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n\n# Redirect Trailing Slashes If Not A Folder...\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_URI} (.+)/$\nRewriteRule ^ %1 [L,R=301]\n\n# Send Requests To Front Controller...\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [L]' > /var/www/html/public/.htaccess

# Generate application key and run post-install scripts
RUN php artisan key:generate --no-interaction || true
RUN composer run-script --no-dev post-autoload-dump || true

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
