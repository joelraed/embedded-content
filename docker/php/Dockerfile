FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev default-mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) gd intl pdo_mysql zip opcache bcmath mbstring \
 && pecl install xdebug && docker-php-ext-enable xdebug \
 && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV APACHE_DOCUMENT_ROOT=/var/www/html/web
RUN sed -ri 's!/var/www/html!/var/www/html/web!g' /etc/apache2/sites-available/*.conf \
 && printf '<Directory /var/www/html/web>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/drupal.conf && a2enconf drupal
COPY xdebug.ini /usr/local/etc/php/conf.d/99-xdebug.ini
WORKDIR /var/www/html
