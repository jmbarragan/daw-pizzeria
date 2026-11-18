<?php

/**
 * Middleware que cierra la sesión de una cuenta del personal dada de baja.
 */

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Comprobar «activo» al entrar no basta: si el administrador da de baja a
 * alguien con la sesión abierta, esa sesión debe dejar de servir en la
 * siguiente petición (CU-08, 3c).
 */
class ExigirCuentaActiva
{
    /**
     * Si la cuenta ya no está activa, cierra la sesión y vuelve al acceso.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $usuario = $peticion->user('personal');

        if ($usuario instanceof Usuario && ! $usuario->activo) {
            Auth::guard('personal')->logout();
            $peticion->session()->invalidate();
            $peticion->session()->regenerateToken();

            return redirect()->route('gestion.acceso')
                ->withErrors(['email' => 'Tu cuenta está desactivada. Habla con el administrador.']);
        }

        return $siguiente($peticion);
    }
}
