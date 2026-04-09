FROM php:8.2-apache

# Enable mysqli extension and configure Apache document root for /public.
RUN docker-php-ext-install mysqli \
    && a2enmod rewrite \
    && { \
        echo 'ServerName localhost'; \
        echo '<Directory /var/www/html/public>'; \
        echo '    AllowOverride All'; \
        echo '    Require all granted'; \
        echo '</Directory>'; \
    } > /etc/apache2/conf-available/dev.conf \
    && a2enconf dev \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
