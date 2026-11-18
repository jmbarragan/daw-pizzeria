<?php

/**
 * Proveedor de servicios de la aplicación.
 */

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el aspecto de la paginación y los límites de peticiones de la aplicación.
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Sin servicios propios que registrar por ahora.
     *
     * @return void
     */
    public function register(): void
    {
        //
    }

    /**
     * Paginación con el aspecto de Bootstrap, para los listados de 15 filas
     * (RNF-03), y límite «acceso»: cinco envíos
     * por minuto y por IP entre los formularios
     * de acceso y de contraseña (RNF-01, CU-08 3b). El sexto vuelve al
     * formulario con un aviso, sin llegar a comprobar las credenciales.
     *
     * @return void
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        RateLimiter::for('acceso', function (Request $peticion) {
            return Limit::perMinute(5)->by($peticion->ip())->response(function () {
                return back()
                    ->withErrors(['email' => 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.'])
                    ->onlyInput('email');
            });
        });
    }
}
