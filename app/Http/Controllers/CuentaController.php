<?php

/**
 * Cuenta del cliente: registro tras el pedido, verificación del correo, acceso y salida (RF-08, CU-07 y CU-08).
 */

namespace App\Http\Controllers;

use App\Http\Requests\Gestion\EntrarRequest;
use App\Http\Requests\RegistroRequest;
use App\Models\Cliente;
use App\Models\Pedido;
use App\Servicios\ControlDeAccesos;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * El registro convierte en cuenta al cliente del pedido que se acaba de
 * enviar en esta sesión: no hace falta volver a escribir nada más que la
 * contraseña. Hasta abrir el enlace del correo no se puede entrar. El acceso
 * comparte con el del personal el límite de intentos y el bloqueo de IP
 * (RNF-01), y solo encuentra cuentas activas: nunca a un invitado que use el
 * mismo correo.
 */
class CuentaController extends Controller
{
    /**
     * Crea la cuenta con el cliente del pedido recibido y manda el correo de verificación.
     *
     * @param  RegistroRequest  $peticion  contraseña validada
     * @return RedirectResponse
     */
    public function registrar(RegistroRequest $peticion): RedirectResponse
    {
        $pedido = Pedido::with('cliente')->find($peticion->session()->get('pedido_recibido'));
        $cliente = $pedido?->cliente;

        if (! $cliente || ! $cliente->esInvitado() || ! $cliente->email || $cliente->anonimizado_en) {
            return redirect()->route('carta');
        }
        if ($this->hayCuentaCon($cliente->email)) {
            return $this->yaHayCuenta();
        }

        try {
            $cliente->forceFill(['contrasena' => (string) $peticion->input('contrasena')])->save();
        } catch (UniqueConstraintViolationException) {
            // otra cuenta con ese correo se ha creado a la vez
            return $this->yaHayCuenta();
        }

        $cliente->enviarVerificacion();

        return redirect()->route('pedido.recibido')
            ->with('estado', "Cuenta creada. Te hemos enviado un correo a {$cliente->email} para confirmarlo: hasta entonces no podrás entrar.");
    }

    /**
     * Marca el correo como verificado si el enlace firmado sigue siendo de ese correo.
     *
     * @param  Cliente  $cliente  cliente de la dirección
     * @param  string  $hash  hash del correo al que se mandó el enlace
     * @return RedirectResponse
     */
    public function verificar(Cliente $cliente, string $hash): RedirectResponse
    {
        if ($cliente->esInvitado() || $cliente->anonimizado_en || ! hash_equals(sha1((string) $cliente->email), $hash)) {
            return redirect()->route('acceder')->withErrors(['email' => 'El enlace ya no sirve. Si tienes cuenta, prueba a entrar.']);
        }

        if (! $cliente->email_verificado_en) {
            $cliente->forceFill(['email_verificado_en' => now()])->save();
        }

        return redirect()->route('acceder')->with('estado', 'Correo confirmado. Ya puedes entrar con tu contraseña.');
    }

    /**
     * Formulario de acceso.
     *
     * @return View
     */
    public function mostrarAcceso(): View
    {
        return view('cuenta.acceso');
    }

    /**
     * Comprueba el correo y la contraseña de una cuenta activa y abre la sesión.
     *
     * @param  EntrarRequest  $peticion  correo y contraseña
     * @param  ControlDeAccesos  $control  fallos por IP y bloqueo
     * @return RedirectResponse
     */
    public function entrar(EntrarRequest $peticion, ControlDeAccesos $control): RedirectResponse
    {
        $correo = (string) $peticion->input('email');
        $credenciales = [
            'email' => $correo,
            'password' => $peticion->input('contrasena'),
            'cuenta' => Cliente::soloCuentasActivas(),
        ];

        if (! Auth::guard('cliente')->attempt($credenciales)) {
            if ($control->registrarFallo($peticion->ip(), $correo)) {
                return redirect()->route('acceder');
            }

            // el mismo mensaje si falla la contraseña o si falta confirmar el correo
            return back()
                ->withErrors(['email' => 'Correo o contraseña incorrectos, o el correo aún no está confirmado.'])
                ->onlyInput('email');
        }

        $control->registrarAcceso($peticion->ip(), $correo);
        $peticion->session()->regenerate();

        return redirect()->intended(route('mis-pedidos'));
    }

    /**
     * Cierra la sesión del cliente; la cesta se va con ella.
     *
     * @param  Request  $peticion  petición en curso
     * @return RedirectResponse
     */
    public function salir(Request $peticion): RedirectResponse
    {
        Auth::guard('cliente')->logout();
        $peticion->session()->invalidate();
        $peticion->session()->regenerateToken();

        return redirect()->route('inicio');
    }

    /**
     * Hay ya una cuenta, verificada o no, con ese correo.
     *
     * @param  string  $correo  correo del pedido
     * @return bool
     */
    private function hayCuentaCon(string $correo): bool
    {
        return Cliente::where('email', $correo)->whereNotNull('contrasena')->exists();
    }

    /**
     * Vuelta a la pantalla del pedido con el aviso de que ya hay cuenta.
     *
     * @return RedirectResponse
     */
    private function yaHayCuenta(): RedirectResponse
    {
        return redirect()->route('pedido.recibido')
            ->with('error', 'Ya hay una cuenta con este correo. Entra con ella para ver tus pedidos.');
    }
}
