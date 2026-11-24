<?php

/**
 * Middleware que no deja usar los formularios de acceso a una IP bloqueada.
 */

namespace App\Http\Middleware;

use App\Servicios\ControlDeAccesos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras dure el bloqueo, la IP ve una página que explica hasta cuándo, o
 * que el bloqueo es permanente y solo lo levanta el administrador (RNF-01).
 */
class RechazarIpBloqueada
{
    /**
     * Crea el middleware con el servicio que consulta los bloqueos.
     *
     * @param  ControlDeAccesos  $control  bloqueos y fallos por IP
     */
    public function __construct(private readonly ControlDeAccesos $control) {}

    /**
     * Responde 403 con la explicación si la IP tiene un bloqueo en vigor.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $bloqueo = $this->control->bloqueoVigente($peticion->ip());

        if ($bloqueo !== null) {
            $vista = $peticion->is('gestion', 'gestion/*') ? 'gestion.ip-bloqueada' : 'ip-bloqueada';

            return response()->view($vista, ['bloqueo' => $bloqueo], 403);
        }

        return $siguiente($peticion);
    }
}
