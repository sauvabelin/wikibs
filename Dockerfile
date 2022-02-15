FROM mediawiki:1.31.16

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

EXPOSE 80/tcp

VOLUME /var/www/html/images

# Copy netbs extension
COPY ./NetBSWikiAuth /var/www/html/extensions/NetBSAuth
RUN composer install --working-dir /var/www/html/extensions/NetBSAuth

# Copy mobile frontend extension
COPY ./MobileFrontend /var/www/html/extensions/MobileFrontend

# Copy skin
COPY ./MinervaNeue /var/www/html/skins/MinervaNeue

# Copy localSettings
COPY ./LocalSettings.php /var/www/html/LocalSettings.php