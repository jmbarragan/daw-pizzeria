<?php

use App\Http\Middleware\ExigirCuentaActiva;
use App\Http\Middleware\ExigirRol;
use App\Http\Middleware\RechazarIpBloqueada;
use App\Http\Middleware\UsarConexionDeGestion;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'conexion.gestion' => UsarConexionDeGestion::class,
            'cuenta.activa' => ExigirCuentaActiva::class,
            'ip.bloqueada' => RechazarIpBloqueada::class,
            'rol' => ExigirRol::class,
        ]);

        // solo se atienden peticiones dirigidas al dominio de APP_URL (fuera de local y pruebas)
        $middleware->trustHosts();

        // el cambio de conexión va antes de «auth», que lee al usuario de la tabla
        $middleware->prependToPriorityList(AuthenticatesRequests::class, UsarConexionDeGestion::class);
        // una IP bloqueada ve su aviso aunque ya haya agotado el límite por minuto
        $middleware->prependToPriorityList(ThrottleRequests::class, RechazarIpBloqueada::class);

        // la zona de clientes cambiará el destino de los invitados cuando exista
        $middleware->redirectGuestsTo(fn () => route('gestion.acceso'));
        $middleware->redirectUsersTo(fn () => route('gestion.pedidos'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
