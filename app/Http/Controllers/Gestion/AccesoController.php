<?php

/**
 * Inicio y cierre de sesión del personal (G-01, CU-08).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\EntrarRequest;
use App\Servicios\ControlDeAccesos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * El personal entra con su correo y su contraseña. Si algo falla, el mensaje
 * no dice qué dato es el incorrecto (CU-08, 3a), y una cuenta dada de baja no
 * puede entrar aunque la contraseña sea buena (3c).
 */
class AccesoController extends Controller
{
    /**
     * Formulario de acceso.
     *
     * @return View
     */
    public function mostrar(): View
    {
        return view('gestion.acceso');
    }

    /**
     * Comprueba las credenciales y abre la sesión en el panel de pedidos.
     *
     * @param  EntrarRequest  $peticion  correo y contraseña ya validados
     * @param  ControlDeAccesos  $control  cuenta de fallos y bloqueos de la IP
     * @return RedirectResponse
     */
    public function entrar(EntrarRequest $peticion, ControlDeAccesos $control): RedirectResponse
    {
        $credenciales = [
            'email' => $peticion->input('email'),
            'password' => $peticion->input('contrasena'),
            'activo' => true,
        ];

        if (! Auth::guard('personal')->attempt($credenciales)) {
            // al décimo fallo la IP queda bloqueada y el acceso muestra el aviso
            if ($control->registrarFallo($peticion->ip(), $credenciales['email'])) {
                return redirect()->route('gestion.acceso');
            }

            return back()
                ->withErrors(['email' => 'Correo o contraseña incorrectos.'])
                ->onlyInput('email');
        }

        $control->registrarAcceso($peticion->ip(), $credenciales['email']);

        // identificador de sesión nuevo para que no sirva uno anterior
        $peticion->session()->regenerate();

        return redirect()->intended(route('gestion.pedidos'));
    }

    /**
     * Cierra la sesión y vuelve al formulario de acceso.
     *
     * @param  Request  $peticion  petición en curso
     * @return RedirectResponse
     */
    public function salir(Request $peticion): RedirectResponse
    {
        Auth::guard('personal')->logout();

        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('gestion.acceso');
    }
}
