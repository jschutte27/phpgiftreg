FROM php:8.2-apache

RUN apt-get update && apt-get install -y unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer files first for better Docker layer caching
COPY composer.json composer.lock* /var/www/html/

# Set working directory
WORKDIR /var/www/html

# Install PHP dependencies (this layer will be cached if composer files don't change)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application files
COPY . /var/www/html/

# Create necessary directories and set permissions
RUN mkdir -p /var/www/html/item_images /var/www/html/cache /var/www/html/configs
RUN chmod 777 /var/www/html/item_images /var/www/html/cache /var/www/html/configs
