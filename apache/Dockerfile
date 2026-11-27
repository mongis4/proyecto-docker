# Imagen oficial de PHP 8.3 con Apache ya integrado
FROM php:8.3-apache

# Instalamos la extensión pdo_mysql (para que PHP hable con MySQL)
# y activamos el módulo headers de Apache
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers

# Copiamos nuestra aplicación a la carpeta pública de Apache
COPY app/ /var/www/html/

# Damos la propiedad al usuario de Apache (www-data) y permisos correctos:
# carpetas 755 y archivos 644 (lectura para todos, escritura solo el dueño)
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \;

# Indicamos que el contenedor escucha en el puerto 80
EXPOSE 80