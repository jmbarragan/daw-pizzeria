<?php

/**
 * Calendario de apertura por fechas (RF-19, CU-16, pantalla G-07).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\TurnoRequest;
use App\Models\Horario;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Se ven 14 días seguidos; una fecha sin turnos está cerrada, como un lunes o
 * un festivo, y dos turnos el mismo día forman un turno partido. Un turno con
 * pedidos no se borra: el sistema explica el motivo.
 */
class HorarioController extends Controller
{
    /**
     * Días que enseña cada página del calendario.
     *
     * @var int
     */
    private const DIAS_POR_PAGINA = 14;

    /**
     * Código de MySQL cuando una clave externa impide borrar una fila.
     *
     * @var int
     */
    private const FILA_CON_HIJOS = 1451;

    /**
     * Código de MySQL de los errores que lanza un disparador con SIGNAL.
     *
     * @var int
     */
    private const AVISO_DE_DISPARADOR = 1644;

    /**
     * Calendario desde la fecha pedida (o hoy) con los turnos de cada día.
     *
     * @param  Request  $peticion  petición con «desde» opcional, en formato Y-m-d
     * @return View
     */
    public function calendario(Request $peticion): View
    {
        $desde = $this->fecha($peticion->query('desde')) ?? today();
        $hasta = $desde->copy()->addDays(self::DIAS_POR_PAGINA - 1);

        $turnos = Horario::withCount('pedidos')
            ->whereBetween('dia', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('dia')->orderBy('hora_apertura')
            ->get()
            ->groupBy(fn (Horario $turno) => $turno->dia->toDateString());

        $dias = collect(range(0, self::DIAS_POR_PAGINA - 1))->map(fn (int $n) => $desde->copy()->addDays($n));

        return view('gestion.horario.calendario', compact('desde', 'hasta', 'dias', 'turnos'));
    }

    /**
     * Formulario de alta, con la fecha del día en que se ha pulsado «Añadir turno».
     *
     * @param  Request  $peticion  petición con «dia» opcional
     * @return View
     */
    public function nuevo(Request $peticion): View
    {
        $dia = $this->fecha($peticion->query('dia')) ?? today();

        return view('gestion.horario.turno', ['turno' => new Horario(['dia' => $dia])]);
    }

    /**
     * Guarda un turno nuevo con el siguiente número libre de su fecha.
     *
     * @param  TurnoRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(TurnoRequest $peticion): RedirectResponse
    {
        $dia = $peticion->dia();
        $numero = (int) Horario::whereDate('dia', $dia)->max('turno') + 1;

        $this->guardarTurno(new Horario(['dia' => $dia, 'turno' => $numero]), $peticion);

        return $this->alCalendario($dia, "Turno {$numero} del ".Carbon::parse($dia)->format('d/m/Y').' añadido.');
    }

    /**
     * Formulario de modificación: solo las horas.
     *
     * @param  Horario  $turno  turno de la dirección
     * @return View
     */
    public function editar(Horario $turno): View
    {
        return view('gestion.horario.turno', ['turno' => $turno]);
    }

    /**
     * Guarda las horas nuevas de un turno.
     *
     * @param  TurnoRequest  $peticion  datos validados
     * @param  Horario  $turno  turno de la dirección
     * @return RedirectResponse
     */
    public function actualizar(TurnoRequest $peticion, Horario $turno): RedirectResponse
    {
        $this->guardarTurno($turno, $peticion);

        return $this->alCalendario($turno->dia->toDateString(),
            "Turno {$turno->turno} del {$turno->dia->format('d/m/Y')} guardado.");
    }

    /**
     * Borra un turno sin pedidos. Si tiene alguno, vuelve al calendario con el motivo.
     *
     * @param  Horario  $turno  turno de la dirección
     * @return RedirectResponse
     *
     * @throws QueryException si MySQL falla por otra causa
     */
    public function borrar(Horario $turno): RedirectResponse
    {
        $dia = $turno->dia->toDateString();
        $nombre = "turno {$turno->turno} del {$turno->dia->format('d/m/Y')}";

        if (! $turno->pedidos()->exists()) {
            try {
                $turno->delete();

                return $this->alCalendario($dia, ucfirst($nombre).' borrado.');
            } catch (QueryException $error) {
                if (($error->errorInfo[1] ?? null) !== self::FILA_CON_HIJOS) {
                    throw $error;
                }
            }
        }

        $pedidos = $turno->pedidos()->count();
        $texto = $pedidos === 1 ? 'un pedido' : "{$pedidos} pedidos";

        return redirect()->route('gestion.horario', ['desde' => $dia])->with('error',
            "No se puede borrar el {$nombre} porque tiene {$texto} con la recogida en él. "
            .'Puedes cambiar sus horas mientras sigan incluyendo las recogidas pendientes.');
    }

    /**
     * Guarda las horas del formulario. Si el disparador de solapes lo rechaza
     * (otro turno guardado a la vez), devuelve el mismo error que la validación.
     *
     * @param  Horario  $turno  turno nuevo o existente
     * @param  TurnoRequest  $peticion  datos validados
     * @return void
     *
     * @throws ValidationException si el disparador encuentra un solape
     * @throws QueryException si MySQL falla por otra causa
     */
    private function guardarTurno(Horario $turno, TurnoRequest $peticion): void
    {
        $turno->hora_apertura = $peticion->input('hora_apertura');
        $turno->hora_cierre = $peticion->input('hora_cierre');

        try {
            $turno->save();
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== self::AVISO_DE_DISPARADOR) {
                throw $error;
            }

            throw ValidationException::withMessages(['hora_apertura' => 'Se solapa con otro turno de ese día.']);
        }
    }

    /**
     * Vuelve al calendario en la página que muestra la fecha, con un aviso.
     *
     * @param  string  $dia  fecha en formato Y-m-d
     * @param  string  $aviso  texto del aviso de resultado
     * @return RedirectResponse
     */
    private function alCalendario(string $dia, string $aviso): RedirectResponse
    {
        return redirect()->route('gestion.horario', ['desde' => $dia])->with('estado', $aviso);
    }

    /**
     * Convierte el texto de la dirección en una fecha, o null si no es válido.
     *
     * @param  mixed  $texto  valor de la dirección
     * @return Carbon|null
     */
    private function fecha(mixed $texto): ?Carbon
    {
        if (! is_string($texto) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $texto) ?: null;
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
