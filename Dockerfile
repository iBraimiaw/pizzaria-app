# Imagem do app web: PHP 8.2 + Apache (servidor web) num container só
FROM php:8.2-apache

# pdo_mysql: driver para o PHP conversar com o MySQL via PDO
RUN docker-php-ext-install pdo_mysql

# mod_rewrite: URLs amigáveis (ex.: /api/produtos)
# mod_headers: permite enviar cabeçalhos de segurança pelo .htaccess
RUN a2enmod rewrite headers

# AllowOverride All: deixa o Apache ler os arquivos .htaccess do projeto
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/

# --- Segurança (módulo de segurança) ---
# expose_php=Off: o PHP para de anunciar a versão no header "X-Powered-By".
# display_errors fica desligado por padrão; o config.php só liga em APP_ENV=dev.
# log_errors=On: erros vão para o log do container (docker compose logs web).
RUN { \
      echo 'expose_php = Off'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/seguranca.ini

# Apache também não revela versão/sistema no header "Server" nem nas páginas de erro
RUN { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
    } > /etc/apache2/conf-available/seguranca.conf && a2enconf seguranca
