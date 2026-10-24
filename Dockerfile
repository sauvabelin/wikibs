# MediaWiki 1.43 on Debian bookworm.
#
# The official mediawiki:1.43 image is based on Debian trixie, which the
# Infomaniak Jelastic platform doesn't support. So we start from the bookworm
# PHP image, set up PHP/Apache like the official image does
# (https://github.com/wikimedia/mediawiki-docker/blob/main/1.43/apache/Dockerfile)
# and copy the MediaWiki code (plain PHP, OS-independent) out of the official
# image. Pulling a fresh mediawiki:1.43 picks up new 1.43.x releases.
FROM php:8.3-apache-bookworm

# System dependencies
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
		librsvg2-bin \
		imagemagick \
		# Required for SyntaxHighlighting
		python3 \
		# Required by composer to extract packages
		unzip \
	; \
	rm -rf /var/lib/apt/lists/*

# PHP extensions needed by MediaWiki (same set as the official image)
RUN set -eux; \
	savedAptMark="$(apt-mark showmanual)"; \
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libicu-dev \
		libonig-dev \
		liblua5.1-0-dev \
	; \
	docker-php-ext-install -j "$(nproc)" \
		calendar \
		intl \
		mbstring \
		mysqli \
		opcache \
	; \
	pecl install APCu-5.1.28; \
	pecl install LuaSandbox-4.1.2; \
	docker-php-ext-enable \
		apcu \
		luasandbox \
	; \
	rm -r /tmp/pear; \
	# Remove build-only packages, keep the runtime libraries the extensions link against
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { so = $(NF-1); if (index(so, "/usr/local/") == 1) { next }; gsub("^/(usr/)?", "", so); printf "*%s\n", so }' \
		| sort -u \
		| xargs -r dpkg-query --search \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# Apache: short URLs, and AllowEncodedSlashes for VisualEditor
RUN set -eux; \
	a2enmod rewrite; \
	{ \
		echo "<Directory /var/www/html>"; \
		echo "  RewriteEngine On"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-f"; \
		echo "  RewriteCond %{REQUEST_FILENAME} !-d"; \
		echo "  RewriteRule ^ %{DOCUMENT_ROOT}/index.php [L]"; \
		echo "</Directory>"; \
	} > "$APACHE_CONFDIR/conf-available/short-url.conf"; \
	a2enconf short-url; \
	sed -i "s/<\/VirtualHost>/\tAllowEncodedSlashes NoDecode\n<\/VirtualHost>/" "$APACHE_CONFDIR/sites-available/000-default.conf"

# Recommended opcache settings
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=60'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# MediaWiki code from the official image
COPY --from=mediawiki:1.43 /var/www/html /var/www/html
RUN chown -R www-data:www-data extensions skins cache images

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