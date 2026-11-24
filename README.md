# La Focaccia di Trevi

Pedidos para recoger de una pizzería pequeña, inventada para el proyecto. El cliente elige en la carta, deja su nombre y su teléfono y sigue el pedido con el enlace que le llega por correo. En el mostrador se acepta, se le da hora de recogida y se cobra cuando pasa a por él.

Proyecto de fin de ciclo de DAW, curso 2026/27.

## Estado

Versión 0.5.0. Ya se puede hacer un pedido de principio a fin: el cliente lo envía desde la carta y lo sigue con su enlace, y el personal lo acepta con una hora, lo prepara y lo cobra al recogerlo. También se apuntan pedidos por teléfono, y el cliente recibe un correo en cada paso. Quien quiera puede crear una cuenta para ver sus pedidos y repetirlos. El personal gestiona además los clientes y consulta los informes, en pantalla o en PDF, y el administrador lleva las cuentas del personal y los ajustes del local. Hay ayuda para el cliente y en cada pantalla de la gestión. Falta la puesta en marcha en el *hosting*, que llega en la 1.0.0.

## Qué hace falta

PHP 8.3 o superior (con `pdo_mysql`, `mbstring`, `intl` y `bcmath`), Composer 2, Node.js 20 o superior y Docker, que pone MySQL 8.4 y Mailpit para ver los correos.

## Arrancarlo en local

Copia `.env.example` a `.env`, rellena las contraseñas de MySQL (también `DB_INFORMES_CONTRASENA`, la del usuario de solo lectura de los informes) y ejecuta `php artisan key:generate`. Luego:

```
docker compose up -d
composer install
npm install
npm run build
php artisan migrate --database=admin
php artisan basedatos:permisos
php artisan db:seed --database=admin
```

La orden de permisos da a cada usuario de MySQL lo suyo y hay que repetirla si una migración añade tablas. Para trabajar hacen falta tres terminales: `php artisan serve` (la web, en http://localhost:8000), `php artisan queue:work` (los correos) y `php artisan schedule:work` (el rechazo de los pedidos pasados, cada 15 minutos). Los correos se ven en Mailpit, en http://localhost:8025.

La cola tiene que trabajar con la conexión de gestión para poder usar el servidor de correo de los ajustes: `DB_CONNECTION=gestion php artisan queue:work` (en PowerShell, `$env:DB_CONNECTION='gestion'` antes de la orden). Con la conexión de la web también funciona, pero los correos salen siempre por las variables `MAIL_*`.

Las cuentas del personal de los datos de prueba están en `database/seeders/PersonalYClientesSeeder.php`.

Las pruebas se lanzan con `php artisan test`.
