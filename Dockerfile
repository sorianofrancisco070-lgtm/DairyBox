# =========================================================
# DairyBox – Dockerfile
# PHP 8.2 + Apache
# =========================================================
FROM php:8.2-apache

# Install PHP extensions and system tools
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql gd zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy all project files
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/assets \
    && chmod -R 775 /var/www/html/config

# Apache config – allow .htaccess overrides
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/dairybox.conf \
    && a2enconf dairybox

# PHP production settings
RUN echo "display_errors = Off\n\
log_errors = On\n\
error_log = /var/log/apache2/php_errors.log\n\
upload_max_filesize = 10M\n\
post_max_size = 10M\n\
memory_limit = 256M\n\
max_execution_time = 60\n\
session.cookie_httponly = 1\n\
session.use_strict_mode = 1" > /usr/local/etc/php/conf.d/dairybox.ini

# Copy and set entrypoint
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port 80
EXPOSE 80

# Start via entrypoint
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
