FROM mediawiki:1.39.12

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

EXPOSE 80/tcp

VOLUME /var/www/html/images

# Mount new NetBS AuthManager extension as volume for development
COPY ./NetBSAuthManager /var/www/html/extensions/NetBSAuthManager
RUN composer install --working-dir /var/www/html/extensions/NetBSAuthManager


# Copy mobile frontend extension
COPY ./MobileFrontend /var/www/html/extensions/MobileFrontend

# Copy code mirror extension
COPY ./CodeMirror /var/www/html/extensions/CodeMirror

# Copy popups extension
COPY ./Popups /var/www/html/extensions/Popups

# Copy logo
COPY ./logo.png /var/www/html/logo.png

# Copy localSettings
COPY ./LocalSettings.php /var/www/html/LocalSettings.php

CMD ["apache2-foreground"]