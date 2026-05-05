FROM php:8.2-apache

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Configurar DocumentRoot a la carpeta public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/*.conf
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copiar el código
COPY . /var/www/html

# Instalar extensión PDO MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Configurar .htaccess por si acaso
RUN echo "RewriteEngine On\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule ^ index.php [QSA,L]" > /var/www/html/public/.htaccess

EXPOSE 80