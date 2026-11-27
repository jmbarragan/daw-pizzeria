<?php

/**
 * Middleware que dice de qué proxies se fía la aplicación.
 */

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies;

/**
 * En el alojamiento, la web está detrás de un túnel que le pasa la IP del
 * cliente y si la petición llegó por HTTPS en las cabeceras X-Forwarded-*. Sin fiarse de él, el
 * bloqueo y los límites por IP caerían sobre el túnel, es decir, sobre todos
 * los clientes a la vez. Solo se cree a las direcciones de
 * app.proxies_de_confianza (PROXIES_DE_CONFIANZA en el .env); en local la
 * lista va vacía y las cabeceras no cuentan, porque cualquiera podría falsearlas.
 */
class ConfiarEnLosProxies extends TrustProxies
{
    /**
     * Direcciones de los proxies de confianza, leídas de la configuración en cada petición.
     *
     * @return list<string>
     */
    protected function proxies()
    {
        $lista = explode(',', (string) config('app.proxies_de_confianza'));

        return array_values(array_filter(array_map('trim', $lista)));
    }
}
