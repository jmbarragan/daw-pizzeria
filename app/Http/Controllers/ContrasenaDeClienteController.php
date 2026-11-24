<?php

/**
 * Recuperación de la contraseña de un cliente con cuenta (RF-08, CU-08 2a).
 */

namespace App\Http\Controllers;

use App\Http\Requests\Gestion\NuevaContrasenaRequest;
use App\Models\Cliente;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Igual que la del personal, con el gestor «clientes» y su tabla propia. Solo
 * se buscan cuentas activas, y la respuesta es la misma tanto si el correo
 * tiene cuenta como si no, para no revelar quién es cliente.
 */
class ContrasenaDeClienteController extends Controller
{
    /**
     * Formulario para pedir el enlace.
     *
     * @return View
     */
    public function olvidada(): View
    {
        return view('cuenta.contrasena-olvidada');
    }

    /**
     * Manda el enlace si el correo es de una cuenta activa.
     *
     * @param  Request  $peticion  petición con «email»
     * @return RedirectResponse
     */
    public function enviarEnlace(Request $peticion): RedirectResponse
    {
        $peticion->validate(['email' => ['required', 'email']]);

        Password::broker('clientes')->sendResetLink([
            'email' => (string) $peticion->input('email'),
            'cuenta' => Cliente::soloCuentasActivas(),
        ]);

        return back()->with('estado', 'Si el correo es de una cuenta, te llegará un enlace para elegir otra contraseña.');
    }

    /**
     * Formulario de la contraseña nueva, al que lleva el enlace del correo.
     *
     * @param  Request  $peticion  petición con el correo en la dirección
     * @param  string  $token  token del enlace
     * @return View
     */
    public function nueva(Request $peticion, string $token): View
    {
        return view('cuenta.contrasena-nueva', ['token' => $token, 'email' => $peticion->query('email')]);
    }

    /**
     * Guarda la contraseña nueva si el token vale para ese correo.
     *
     * @param  NuevaContrasenaRequest  $peticion  token, correo y contraseña confirmada
     * @return RedirectResponse
     */
    public function guardar(NuevaContrasenaRequest $peticion): RedirectResponse
    {
        $datos = [
            'email' => (string) $peticion->input('email'),
            'token' => (string) $peticion->input('token'),
            'password' => (string) $peticion->input('contrasena'),
            'cuenta' => Cliente::soloCuentasActivas(),
        ];

        $resultado = Password::broker('clientes')->reset($datos, function (Cliente $cliente, string $contrasena) {
            $cliente->forceFill(['contrasena' => $contrasena])->save();

            event(new PasswordReset($cliente));
        });

        if ($resultado !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => 'El enlace no es válido o ha caducado. Pide otro.'])->onlyInput('email');
        }

        return redirect()->route('acceder')->with('estado', 'Contraseña cambiada. Ya puedes entrar con la nueva.');
    }
}
