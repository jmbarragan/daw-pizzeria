# La Focaccia di Trevi

Pedidos para recoger de una pizzería pequeña, inventada para el proyecto. El cliente elige en la carta, deja su nombre y su teléfono y sigue el pedido con el enlace que le llega por correo. En el mostrador se acepta, se le da hora de recogida y se cobra cuando pasa a por él.

Proyecto de fin de ciclo de DAW, curso 2026/27.

## Estado

Versión 0.6.0. Ya se puede hacer un pedido de principio a fin: el cliente lo envía desde la carta y lo sigue con su enlace, y el personal lo acepta con una hora, lo prepara y lo cobra al recogerlo. También se apuntan pedidos por teléfono, y el cliente recibe un correo en cada paso. Quien quiera puede crear una cuenta para ver sus pedidos y repetirlos. El personal gestiona además los clientes y consulta los informes, en pantalla o en PDF, y el administrador lleva las cuentas del personal y los ajustes del local. Hay ayuda para el cliente y en cada pantalla de la gestión. Se instala con `make start` (en Windows, `iniciar.ps1`), que pone Docker si hace falta. La versión publicada está en https://pizzeria-daw.barraganes.es. Las pantallas públicas pasan el W3C y el TAW, con contraste AA.

## Ponerlo en marcha

Con una sola orden, desde la carpeta del proyecto:

```
make start
```

En Windows, que no trae `make`:

```
powershell -ExecutionPolicy Bypass -File iniciar.ps1
```

Si falta Docker, lo instala después de preguntar (en Windows, con `winget`; Docker Desktop puede pedir permisos de administrador y reiniciar el equipo, y luego se vuelve a lanzar la orden). La primera vez crea el `.env` con contraseñas nuevas, construye la aplicación, prepara la base de datos con los datos de prueba y abre el navegador en http://localhost:8000. Los correos se ven en Mailpit, en http://localhost:8025.

- `make stop` (o `iniciar.ps1 stop`) la para sin borrar nada.
- `make reset` (o `iniciar.ps1 reset`) borra la base de datos y vuelve a empezar con los datos de prueba.

En Docker funcionan la web, la cola de correos (con la conexión de gestión) y el planificador, más MySQL 8.4 y Mailpit.

## Trabajar sin Docker para la aplicación

Hace falta PHP 8.3 o superior (con `pdo_mysql`, `mbstring`, `intl` y `bcmath`), Composer 2 y Node.js 20 o superior. Docker solo pone MySQL y Mailpit.

Copia `.env.example` a `.env`, rellena las contraseñas de MySQL (también `DB_INFORMES_CONTRASENA`, la del usuario de solo lectura de los informes) y ejecuta `php artisan key:generate`. Luego:

```
docker compose up -d mysql mailpit
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
