FROM serversideup/php:8.3-fpm-nginx

# Switch to root so we can do root things
USER root

# Install the intl extension with root permissions
RUN install-php-extensions intl xsl gd

# Copy XDebug.ini to container
# COPY xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Drop back to our unprivileged user
USER www-data
