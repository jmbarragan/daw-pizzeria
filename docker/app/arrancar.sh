#!/bin/sh
# Arranque de la web en Docker. Prepara la base de datos y pone en marcha Apache.
# Cada vez aplica las migraciones nuevas y vuelve a dar los permisos, por si
# alguna migración ha añadido tablas. Si la base no tiene datos, carga los de prueba.
set -e

php artisan migrate --database=admin --force
php artisan basedatos:permisos

# Sin ninguna cuenta del personal, la base está recién creada (o se cortó antes de terminar)
hay_personal=$(php artisan tinker --execute="echo DB::connection('admin')->table('usuarios')->exists() ? 'si' : 'no';")
if [ "$hay_personal" != si ]; then
    php artisan db:seed --database=admin --force
fi

# Las órdenes anteriores corren como root; Apache, la cola y el planificador, como www-data
chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
