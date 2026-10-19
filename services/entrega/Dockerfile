# Microserviço de ENTREGA: container próprio, código próprio, sem banco de dados.
# Ele só sabe calcular distância, frete e tempo — nada mais (responsabilidade única).
FROM php:8.2-apache

# mod_rewrite não é necessário: usamos FallbackResource (ver apache.conf),
# que manda toda URL que não é arquivo para o public/index.php (front controller).
COPY apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www
COPY src/ /var/www/src/
COPY public/ /var/www/public/

# Ao iniciar, grava o horário de início (usado no "uptime" do /health)
# e depois sobe o Apache normalmente em primeiro plano.
CMD ["sh", "-c", "date +%s > /tmp/iniciado_em && exec apache2-foreground"]
