FROM php:8.2-apache

# Copy your project files
COPY . /var/www/html/

# Enable Apache rewrite
RUN a2enmod rewrite

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80