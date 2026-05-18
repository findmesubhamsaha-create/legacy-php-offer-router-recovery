FROM php:8.2-apache

# Install mysqli (required by connect.php) and pdo_mysql (forward-compat)
RUN docker-php-ext-install mysqli pdo_mysql \
    && a2enmod rewrite headers

# Replace default VirtualHost: set DocumentRoot, DirectoryIndex, AllowOverride
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    DocumentRoot /var/www/html'; \
    echo '    DirectoryIndex index.php index.html'; \
    echo '    <Directory /var/www/html>'; \
    echo '        Options Indexes FollowSymLinks'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '    </Directory>'; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
