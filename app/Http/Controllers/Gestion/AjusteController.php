<?php

/**
 * Ajustes del local y bloqueos de IP, solo para el administrador (RF-21, pantalla G-10).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\AjustesRequest;
use App\Models\Ajuste;
use App\Models\BloqueoIp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Una sola pantalla con los datos del local que ve el cliente (teléfono,
 * dirección y política de privacidad), el servidor de correo y los bloqueos
 * de IP. Cada ajuste guarda quién lo cambió y cuándo; cada desbloqueo, lo
 * mismo. La contraseña del correo va cifrada con la clave de la aplicación y
 * no sale nunca en la pantalla.
 */
class AjusteController extends Controller
{
    /**
     * Ajustes que se editan aquí, con su contraseña aparte.
     *
     * @var list<string>
     */
    private const EDITABLES = ['telefono_local', 'direccion_local', 'politica_privacidad', 'smtp_servidor', 'smtp_puerto', 'smtp_usuario'];

    /**
     * Formulario de ajustes y bloqueos de los últimos 30 días, los vigentes primero.
     *
     * @return View
     */
    public function mostrar(): View
    {
        $ajustes = Ajuste::whereIn('clave', [...self::EDITABLES, 'smtp_contrasena'])->get()->keyBy('clave');

        return view('gestion.ajustes', [
            'valores' => $ajustes->map->valor->except('smtp_contrasena'),
            'hayContrasena' => (bool) $ajustes->get('smtp_contrasena')?->valor,
            'cambiado' => $ajustes->sortByDesc('actualizado_en')->first(),
            'bloqueos' => BloqueoIp::with('desbloqueadoPor')
                ->where('inicio', '>=', now()->subDays(30))
                ->orderByRaw('desbloqueado_en IS NOT NULL, inicio DESC')
                ->get(),
        ]);
    }

    /**
     * Guarda los ajustes que han cambiado, con quién y cuándo, en una transacción.
     *
     * @param  AjustesRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(AjustesRequest $peticion): RedirectResponse
    {
        $nuevos = $peticion->only(self::EDITABLES);
        if ($peticion->filled('smtp_contrasena')) {
            $nuevos['smtp_contrasena'] = Crypt::encryptString((string) $peticion->input('smtp_contrasena'));
        }

        $quien = $peticion->user('personal')->id;
        DB::transaction(function () use ($nuevos, $quien) {
            foreach (Ajuste::whereIn('clave', array_keys($nuevos))->get() as $ajuste) {
                $ajuste->valor = (string) $nuevos[$ajuste->clave];
                if ($ajuste->isDirty('valor')) {
                    $ajuste->actualizado_por = $quien;
                    $ajuste->save();
                }
            }
        });

        return redirect()->route('gestion.ajustes')->with('estado', 'Ajustes guardados.');
    }

    /**
     * Levanta un bloqueo de IP vigente, apuntando quién y cuándo.
     *
     * @param  Request  $peticion  petición con el administrador de la sesión
     * @param  BloqueoIp  $bloqueo  bloqueo de la dirección
     * @return RedirectResponse
     */
    public function desbloquear(Request $peticion, BloqueoIp $bloqueo): RedirectResponse
    {
        // solo uno sin levantar: si otro administrador lo acaba de hacer, no se pisa su nombre
        $hecho = BloqueoIp::whereKey($bloqueo->id)->whereNull('desbloqueado_en')
            ->update(['desbloqueado_por' => $peticion->user('personal')->id, 'desbloqueado_en' => now()]);

        return redirect()->route('gestion.ajustes')->with($hecho
            ? ['estado' => "IP {$bloqueo->ip} desbloqueada."]
            : ['error' => "La IP {$bloqueo->ip} ya estaba desbloqueada."]);
    }
}
