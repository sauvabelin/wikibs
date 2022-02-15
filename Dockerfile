FROM mediawiki:1.31.16-fpm-alpine

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

VOLUME /var/www/html/images

# Copy netbs extension
COPY ./NetBSWikiAuth /var/www/html/extensions/NetBSAuth
RUN composer install --working-dir /var/www/html/extensions/NetBSAuth --ignore-platform-reqs

# Copy mobile frontend extension
COPY ./MobileFrontend /var/www/html/extensions/MobileFrontend

# Copy localSettings
COPY ./LocalSettings.php /var/www/html/LocalSettings.php