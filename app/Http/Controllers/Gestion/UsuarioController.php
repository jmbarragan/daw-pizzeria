<?php

/**
 * Gestión de los usuarios del personal, solo para el administrador (RF-21, CU-20, pantalla G-09).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\UsuarioRequest;
use App\Models\Usuario;
use App\Notifications\AltaDelPersonal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Listado con búsqueda por nombre o correo, alta, modificación, desactivación
 * y reactivación; no se borra nadie, para conservar quién decidió cada pedido.
 * Siempre queda al menos un administrador activo (CU-20, 5a): el último no se
 * desactiva ni pierde el rol, y la comprobación bloquea las filas de los
 * administradores para que dos cambios a la vez no los dejen a cero. La cuenta
 * «Rechazo automático» (decisión 030) no sale ni se toca.
 */
class UsuarioController extends Controller
{
    /**
     * Cuentas del personal por nombre, con búsqueda por nombre o correo.
     *
     * @param  Request  $peticion  petición con «buscar»
     * @return View
     */
    public function listado(Request $peticion): View
    {
        $buscar = trim((string) $peticion->query('buscar', ''));
        $consulta = Usuario::where('email', '!=', Usuario::CORREO_DEL_RECHAZO_AUTOMATICO)->orderBy('nombre');

        if ($buscar !== '') {
            $patron = '%'.addcslashes($buscar, '\\%_').'%';
            $consulta->where(fn ($donde) => $donde->where('nombre', 'like', $patron)->orWhere('email', 'like', $patron));
        }

        return view('gestion.usuarios', ['usuarios' => $consulta->paginate(15)->withQueryString(), 'buscar' => $buscar]);
    }

    /**
     * Formulario de alta, como empleado.
     *
     * @return View
     */
    public function nuevo(): View
    {
        return view('gestion.usuario', ['usuario' => new Usuario(['rol' => Usuario::ROL_EMPLEADO])]);
    }

    /**
     * Da de alta la cuenta, activa y con una contraseña aleatoria que nadie
     * conoce, y manda al empleado el enlace para elegir la suya.
     *
     * @param  UsuarioRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(UsuarioRequest $peticion): RedirectResponse
    {
        $usuario = Usuario::create($peticion->datos() + ['contrasena' => Str::password(40), 'activo' => true]);
        $usuario->notify(new AltaDelPersonal(Password::broker('personal')->createToken($usuario)));

        return redirect()->route('gestion.usuarios')
            ->with('estado', "Cuenta de «{$usuario->nombre}» creada. Le hemos mandado a {$usuario->email} el enlace para elegir su contraseña.");
    }

    /**
     * Formulario de modificación.
     *
     * @param  Usuario  $usuario  cuenta de la dirección
     * @return View
     *
     * @throws HttpException 404 si es la cuenta del rechazo automático
     */
    public function editar(Usuario $usuario): View
    {
        $this->noEsLaCuentaAutomatica($usuario);

        return view('gestion.usuario', ['usuario' => $usuario]);
    }

    /**
     * Guarda nombre, correo y rol; al último administrador activo no se le quita el rol.
     *
     * @param  UsuarioRequest  $peticion  datos validados
     * @param  Usuario  $usuario  cuenta de la dirección
     * @return RedirectResponse
     *
     * @throws HttpException 404 si es la cuenta del rechazo automático
     */
    public function actualizar(UsuarioRequest $peticion, Usuario $usuario): RedirectResponse
    {
        $this->noEsLaCuentaAutomatica($usuario);
        $datos = $peticion->datos();

        $guardado = $this->sinDejarSinAdministrador($usuario, $datos['rol'] !== Usuario::ROL_ADMINISTRADOR,
            fn () => $usuario->update($datos));

        return $guardado
            ? redirect()->route('gestion.usuarios')->with('estado', "Cuenta de «{$usuario->nombre}» guardada.")
            : back()->withInput()->with('error', 'Es el único administrador activo: antes de quitarle el rol, haz administradora a otra persona.');
    }

    /**
     * Desactiva la cuenta, que conserva su historial; su sesión se cierra en la siguiente petición.
     *
     * @param  Request  $peticion  petición con el administrador de la sesión
     * @param  Usuario  $usuario  cuenta de la dirección
     * @return RedirectResponse
     *
     * @throws HttpException 404 si es la cuenta del rechazo automático
     */
    public function desactivar(Request $peticion, Usuario $usuario): RedirectResponse
    {
        $this->noEsLaCuentaAutomatica($usuario);

        if ($usuario->is($peticion->user('personal'))) {
            return back()->with('error', 'No puedes desactivar tu propia cuenta; si hace falta, que lo haga otro administrador.');
        }

        $hecho = $this->sinDejarSinAdministrador($usuario, true, fn () => $usuario->update(['activo' => false]));

        return $hecho
            ? back()->with('estado', "Cuenta de «{$usuario->nombre}» desactivada.")
            : back()->with('error', 'Es el único administrador activo: no se puede desactivar.');
    }

    /**
     * Vuelve a activar una cuenta desactivada.
     *
     * @param  Usuario  $usuario  cuenta de la dirección
     * @return RedirectResponse
     *
     * @throws HttpException 404 si es la cuenta del rechazo automático
     */
    public function activar(Usuario $usuario): RedirectResponse
    {
        $this->noEsLaCuentaAutomatica($usuario);
        $usuario->update(['activo' => true]);

        return back()->with('estado', "Cuenta de «{$usuario->nombre}» activada.");
    }

    /**
     * Aplica el cambio en una transacción que bloquea a los administradores
     * activos. Si el cambio le quita a esta cuenta la condición de
     * administrador activo y es la única que la tiene, no se aplica.
     *
     * @param  Usuario  $usuario  cuenta que cambia
     * @param  bool  $dejaDeSerloSiLoEra  verdadero si el cambio le quita el rol de administrador o la desactiva
     * @param  callable(): mixed  $cambio  cambio que se aplica
     * @return bool false si dejaba la gestión sin administrador
     */
    private function sinDejarSinAdministrador(Usuario $usuario, bool $dejaDeSerloSiLoEra, callable $cambio): bool
    {
        return DB::transaction(function () use ($usuario, $dejaDeSerloSiLoEra, $cambio) {
            $activos = Usuario::where('rol', Usuario::ROL_ADMINISTRADOR)->where('activo', true)->lockForUpdate()->pluck('id');

            if ($dejaDeSerloSiLoEra && $activos->all() === [$usuario->id]) {
                return false;
            }
            $cambio();

            return true;
        });
    }

    /**
     * La cuenta del rechazo automático responde con un 404, como si no existiera.
     *
     * @param  Usuario  $usuario  cuenta de la dirección
     * @return void
     *
     * @throws HttpException 404 si es la cuenta del rechazo automático
     */
    private function noEsLaCuentaAutomatica(Usuario $usuario): void
    {
        abort_if($usuario->email === Usuario::CORREO_DEL_RECHAZO_AUTOMATICO, 404);
    }
}
