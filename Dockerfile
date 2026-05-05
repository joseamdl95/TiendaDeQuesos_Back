FROM php:8.2-apache

# Habilitar mod_rewrite (necesario para el enrutamiento de index.php)
RUN a2enmod rewrite

# Copiar todo el código al contenedor
COPY . /var/www/html/

# Configurar Apache para usar public/ como directorio raíz
RUN mv /var/www/html/public /var/www/html/public_temp \
    && rm -rf /var/www/html/* \
    && mv /var/www/html/public_temp/* /var/www/html/ \
    && rm -rf /var/www/html/public_temp

# Configurar .htaccess manualmente (por si acaso)
RUN echo "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ index.php [QSA,L]" > /var/www/html/.htaccess

# Instalar extensión PDO MySQL (necesaria para la conexión a BD)
RUN docker-php-ext-install pdo pdo_mysql

# Puerto que usará Render
EXPOSE 80