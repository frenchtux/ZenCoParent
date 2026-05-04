FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    libpq-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install \
    pdo_pgsql \
    pdo_sqlite \
    opcache \
    intl \
    zip \
    mbstring \
    pcntl

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Clean up build dependencies
RUN apk del $PHPIZE_DEPS \
    && rm -rf /tmp/pear /var/cache/apk/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create working directory with proper ownership
RUN chown -R www-data:www-data /var/www/html

# Switch to non-root user
USER www-data
