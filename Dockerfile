FROM php:8.2-apache

# 1. Instalar dependencias del sistema y extensiones de PHP requeridas por CodeIgniter 4
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libonig-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        mbstring \
        mysqli \
        pdo_mysql \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# 2. Habilitar módulos de Apache (mod_rewrite y headers)
RUN a2enmod rewrite headers

# 3. Configurar Apache para permitir .htaccess en /var/www/html y proteger carpetas internas de CI4
RUN printf '<VirtualHost *:80>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    <DirectoryMatch "^/var/www/html/(app|system|writable|tests|vendor)">\n\
        Require all denied\n\
    </DirectoryMatch>\n\
    <FilesMatch "^(\\.env|composer\\.(json|lock)|spark|phpunit\\.xml\\.dist)$">\n\
        Require all denied\n\
    </FilesMatch>\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n' > /etc/apache2/sites-available/000-default.conf

# 4. Instalar Composer desde la imagen oficial
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# 5. Copiar composer.json y composer.lock primero para aprovechar caché de Docker
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

# 6. Copiar el resto del código de la aplicación
COPY . .

# 7. Asegurar que existan las subcarpetas de writable/ y asignar permisos a www-data
RUN mkdir -p writable/cache writable/logs writable/session writable/uploads writable/debugbar \
    && chown -R www-data:www-data /var/www/html/writable \
    && chmod -R 775 /var/www/html/writable

EXPOSE 80

CMD ["apache2-foreground"]
