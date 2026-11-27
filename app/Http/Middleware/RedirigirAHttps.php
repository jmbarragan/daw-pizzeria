<?php

/**
 * Middleware que lleva a HTTPS las visitas que llegan por HTTP en el alojamiento.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si APP_URL empieza por https, una página pedida por http se redirige a la
 * misma dirección con https (301), para que la sesión y las contraseñas no
 * viajen sin cifrar. En local APP_URL va con http y no cambia nada. Detrás del
 * túnel, el esquema real lo dice X-Forwarded-Proto (ConfiarEnLosProxies).
 */
class RedirigirAHttps
{
    /**
     * Redirige a https o deja seguir la petición.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $conHttps = str_starts_with((string) config('app.url'), 'https://');

        if ($conHttps && ! $peticion->secure()) {
            return redirect()->secure($peticion->getRequestUri(), 301);
        }

        return $siguiente($peticion);
    }
}
