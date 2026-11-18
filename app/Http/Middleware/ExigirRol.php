<?php

/**
 * Middleware que limita una ruta de gestión a un rol del personal.
 */

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deja pasar solo al personal con el rol indicado en la ruta, por ejemplo
 * «rol:administrador» en usuarios y ajustes (RF-21). A los demás les responde
 * 403, que muestra la página de «sin permiso» (CU-20, excepción 1a).
 */
class ExigirRol
{
    /**
     * Comprueba el rol de quien ha iniciado sesión en la zona de gestión.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @param  string  $rol  rol exigido: administrador o empleado
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente, string $rol): Response
    {
        $usuario = $peticion->user('personal');

        abort_unless($usuario instanceof Usuario && $usuario->rol === $rol, 403);

        return $siguiente($peticion);
    }
}
