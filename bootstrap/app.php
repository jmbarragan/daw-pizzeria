<?php

use App\Http\Middleware\ConfiarEnLosProxies;
use App\Http\Middleware\ExigirCuentaActiva;
use App\Http\Middleware\ExigirCuentaDeCliente;
use App\Http\Middleware\ExigirRol;
use App\Http\Middleware\RechazarIpBloqueada;
use App\Http\Middleware\RedirigirAHttps;
use App\Http\Middleware\UsarConexionDeGestion;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
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
            'cuenta.cliente' => ExigirCuentaDeCliente::class,
            'ip.bloqueada' => RechazarIpBloqueada::class,
            'rol' => ExigirRol::class,
        ]);

        // Solo se atienden peticiones dirigidas al dominio de APP_URL (fuera de local y pruebas), y a localhost,
        // que es como pregunta la comprobación de salud de Docker desde dentro del contenedor.
        $middleware->trustHosts(at: ['^localhost$']);

        // IP del cliente y HTTPS detrás del túnel del alojamiento (PROXIES_DE_CONFIANZA)
        $middleware->replace(TrustProxies::class, ConfiarEnLosProxies::class);
        // con APP_URL en https, las páginas pedidas por http se redirigen (la ruta de salud /up queda fuera)
        $middleware->web(prepend: RedirigirAHttps::class);

        // el cambio de conexión va antes de «auth», que lee al usuario de la tabla
        $middleware->prependToPriorityList(AuthenticatesRequests::class, UsarConexionDeGestion::class);
        // una IP bloqueada ve su aviso aunque ya haya agotado el límite por minuto
        $middleware->prependToPriorityList(ThrottleRequests::class, RechazarIpBloqueada::class);

        // cada zona manda a su propio acceso o a su propia página de inicio
        $middleware->redirectGuestsTo(fn (Request $peticion) => $peticion->is('gestion', 'gestion/*')
            ? route('gestion.acceso') : route('acceder'));
        $middleware->redirectUsersTo(fn (Request $peticion) => $peticion->is('gestion', 'gestion/*')
            ? route('gestion.pedidos') : route('mis-pedidos'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
