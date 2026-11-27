<?php

/**
 * Middleware que cambia la conexión de la base de datos en la zona de gestión.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * La aplicación entra por defecto con el usuario de MySQL de la zona pública
 * (focaccia_web). En las rutas de gestión se pasa a focaccia_gestion, que
 * puede tocar la carta, los pedidos y el personal pero no borrar el histórico
 * (RNF-11).
 */
class UsarConexionDeGestion
{
    /**
     * Pone «gestion» como conexión por defecto durante la petición.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        DB::setDefaultConnection('gestion');

        return $siguiente($peticion);
    }
}
