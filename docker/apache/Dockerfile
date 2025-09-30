FROM php:8.2-apache

# Install system dependencies
# RUN apt-get update && apt-get install -y \
#     git \
#     curl \
#     libpng-dev \
#     libonig-dev \
#     libxml2-dev \
#     zip \
#     unzip \
#     libzip-dev \
#     && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
#     && docker-php-ext-install pdo pdo_pgsql
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libpq-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && docker-php-ext-install pdo pdo_pgsql

# Install Redis extension (phpredis)
RUN pecl install redis \
    && docker-php-ext-enable redis

# Enable Apache modules
RUN a2enmod rewrite headers expires

# Set www-data UID/GID
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Copy Apache configuration
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Install PHP dependencies via Composer
RUN composer install --no-interaction --optimize-autoloader

# Expose port
EXPOSE 80

# Run Apache
CMD ["apache2-foreground"]
