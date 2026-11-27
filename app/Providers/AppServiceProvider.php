<?php

/**
 * Paginación con Bootstrap, servidor de correo de los ajustes y límites de peticiones del acceso y de los pedidos.
 */

namespace App\Providers;

use App\Http\Controllers\PedidoController;
use App\Servicios\CorreoSaliente;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Registra el aspecto de la paginación, el servidor de correo de los ajustes y
 * los límites de peticiones de la aplicación.
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
     * Arranque de la aplicación:
     *
     * - paginación con el aspecto de Bootstrap, para los listados de 15 filas (RNF-03);
     * - antes de cada correo, el servidor de los ajustes, si está completo (CorreoSaliente);
     * - límite «acceso»: cinco envíos por minuto y por IP entre los formularios de acceso y de
     *   contraseña (RNF-01, CU-08 3b); el sexto vuelve con un aviso, sin comprobar las credenciales;
     * - límite «pedidos»: cinco envíos de pedidos por minuto y por IP.
     *
     * @return void
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // @csrf sin autocomplete="off": en un campo oculto ese atributo no es HTML válido (RNF-04). Laravel lo pone
        // para que Firefox no recupere un token viejo al volver atrás; si pasa, sale la página «caducada» (419).
        Blade::directive('csrf', fn () => '<input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">');

        // cada correo sale por el servidor de los ajustes si están completos
        Event::listen(NotificationSending::class, function (NotificationSending $envio): void {
            if ($envio->channel === 'mail') {
                app(CorreoSaliente::class)->aplicar();
            }
        });

        RateLimiter::for('acceso', function (Request $peticion) {
            return Limit::perMinute(5)->by($peticion->ip())->response(function () {
                return back()
                    ->withErrors(['email' => 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.'])
                    ->onlyInput('email');
            });
        });

        // cada pedido manda un correo: sin límite, el formulario serviría para escribir a cualquiera.
        // El límite por correo va en PedidoController, contando solo los pedidos guardados.
        RateLimiter::for('pedidos', function (Request $peticion) {
            return Limit::perMinute(5)->by('ip:'.$peticion->ip())->response(fn () => back()->withInput()
                ->with('error', PedidoController::AVISO_DE_LIMITE));
        });
    }
}
