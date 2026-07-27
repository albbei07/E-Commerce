FROM php:8.2-apache

# Estensione PDO MySQL richiesta da db.php
RUN docker-php-ext-install pdo pdo_mysql

# Abilita mod_rewrite (utile se in futuro si aggiungono URL puliti)
RUN a2enmod rewrite

# Copia il codice dell'applicazione (ora dentro src/) nella document root di Apache
COPY src/ /var/www/html/

# Permessi corretti per l'utente con cui gira Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
