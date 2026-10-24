FROM php:8.2-apache

# Install MySQL support for PHP and enable Apache rewrite rules
RUN docker-php-ext-install pdo_mysql && a2enmod rewrite

# Allow the project's .htaccess rules to work
RUN printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' > /etc/apache2/conf-available/campusride.conf \
    && a2enconf campusride

# Copy CampusRide into the Apache web directory
COPY . /var/www/html/

# Set file ownership
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
