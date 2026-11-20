# La Focaccia di Trevi

Pedidos para recoger de una pizzería pequeña, inventada para el proyecto. El cliente elige en la carta, deja su nombre y su teléfono y sigue el pedido con el enlace que le llega por correo. En el mostrador se acepta, se le da hora de recogida y se cobra cuando pasa a por él.

Proyecto de fin de ciclo de DAW, curso 2026/27.

## Estado

Versión 0.4.0. Ya se puede hacer un pedido de principio a fin: el cliente lo envía desde la carta y lo sigue con su enlace, y el personal lo acepta con una hora, lo prepara y lo cobra al recogerlo. También se apuntan pedidos por teléfono. El cliente recibe un correo cuando llega el pedido, cuando se acepta y cuando se rechaza, y los pendientes que se quedan sin hora en su día se rechazan solos. Faltan la administración de usuarios y de ajustes, que llegan en la 0.5.0.

## Qué hace falta

PHP 8.3 o superior (con `pdo_mysql`, `mbstring`, `intl` y `bcmath`), Composer 2, Node.js 20 o superior y Docker, que pone MySQL 8.4 y Mailpit para ver los correos.

## Arrancarlo en local

Copia `.env.example` a `.env`, rellena las contraseñas de MySQL y ejecuta `php artisan key:generate`. Luego:

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

Las cuentas del personal de los datos de prueba están en `database/seeders/PersonalYClientesSeeder.php`.

Las pruebas se lanzan con `php artisan test`.
