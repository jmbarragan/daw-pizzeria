<?php

/**
 * Cierra la sesión de un cliente que ya no puede entrar con su cuenta.
 */

namespace App\Http\Middleware;

use App\Models\Cliente;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si el personal anonimiza a un cliente con la sesión abierta, su cuenta deja
 * de tener contraseña y correo. En la siguiente petición se cierra la sesión,
 * igual que con una cuenta del personal desactivada.
 */
class ExigirCuentaDeCliente
{
    /**
     * Deja pasar solo a un cliente con cuenta y sin anonimizar.
     *
     * @param  Request  $peticion  petición en curso
     * @param  Closure(Request): Response  $siguiente  resto de la cadena
     * @return Response
     */
    public function handle(Request $peticion, Closure $siguiente): Response
    {
        $cliente = $peticion->user('cliente');

        if ($cliente instanceof Cliente && ($cliente->esInvitado() || $cliente->anonimizado_en)) {
            Auth::guard('cliente')->logout();
            $peticion->session()->invalidate();
            $peticion->session()->regenerateToken();

            return redirect()->route('acceder')->withErrors(['email' => 'Tu cuenta ya no está activa.']);
        }

        return $siguiente($peticion);
    }
}
