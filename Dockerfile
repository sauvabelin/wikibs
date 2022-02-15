FROM mediawiki:1.31.16-fpm-alpine

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

VOLUME /var/www/html/images

# Copy extension
COPY ./NetBSWikiAuth /var/www/html/extensions/NetBSWikiAuth
RUN composer install --working-dir /var/www/html/extensions/NetBSWikiAuth

# Copy localSettings
COPY ./LocalSettings.php /var/www/html/LocalSettings.php