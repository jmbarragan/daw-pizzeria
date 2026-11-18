<?php

/**
 * Recuperación de la contraseña del personal por correo (RF-10, CU-08 2a).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\NuevaContrasenaRequest;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * Dos pasos: pedir el enlace con el correo y, desde el enlace, elegir la
 * contraseña nueva. La respuesta al pedir el enlace es siempre la misma, esté
 * o no el correo dado de alta, para no revelar quién trabaja en la pizzería.
 */
class ContrasenaController extends Controller
{
    /**
     * Formulario para pedir el enlace.
     *
     * @return View
     */
    public function olvidada(): View
    {
        return view('gestion.contrasena-olvidada');
    }

    /**
     * Envía el enlace si el correo es de alguien del personal.
     *
     * @param  Request  $peticion  petición con el correo
     * @return RedirectResponse
     */
    public function enviarEnlace(Request $peticion): RedirectResponse
    {
        $peticion->validate(['email' => ['required', 'email']]);

        Password::broker('personal')->sendResetLink($peticion->only('email'));

        return back()->with('estado', 'Si el correo es del personal, te llegará un enlace para elegir otra contraseña.');
    }

    /**
     * Formulario de contraseña nueva al que lleva el enlace del correo.
     *
     * @param  Request  $peticion  petición con el correo en la dirección
     * @param  string  $token  token del enlace
     * @return View
     */
    public function nueva(Request $peticion, string $token): View
    {
        return view('gestion.contrasena-nueva', [
            'token' => $token,
            'email' => $peticion->query('email'),
        ]);
    }

    /**
     * Guarda la contraseña nueva si el enlace sigue siendo válido.
     *
     * @param  NuevaContrasenaRequest  $peticion  token, correo y contraseña validados
     * @return RedirectResponse
     */
    public function guardar(NuevaContrasenaRequest $peticion): RedirectResponse
    {
        $datos = [
            'email' => $peticion->input('email'),
            'token' => $peticion->input('token'),
            'password' => $peticion->input('contrasena'),
        ];

        $resultado = Password::broker('personal')->reset($datos, function (Usuario $usuario, string $contrasena) {
            // el cast «hashed» del modelo guarda el hash, no el texto
            $usuario->forceFill(['contrasena' => $contrasena])->save();

            event(new PasswordReset($usuario));
        });

        if ($resultado !== Password::PASSWORD_RESET) {
            return back()
                ->withErrors(['email' => 'El enlace no es válido o ha caducado. Pide otro.'])
                ->onlyInput('email');
        }

        return redirect()->route('gestion.acceso')
            ->with('estado', 'Contraseña cambiada. Ya puedes entrar con la nueva.');
    }
}
