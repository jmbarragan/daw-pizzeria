<?php

/**
 * Validación del formulario de turnos del horario (RF-19, pantalla G-07).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Horario;
use App\Models\Pedido;
use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Un turno es una fecha con su hora de apertura y de cierre. En el alta se
 * elige la fecha, que no puede haber pasado; al editar solo cambian las horas,
 * porque mover un turno de día cambiaría la recogida de sus pedidos.
 *
 * Antes de guardar se comprueban dos cosas que, si no, acabarían en un error
 * de MySQL: que no se solape con otro turno del mismo día (el disparador lo
 * impide igualmente) y que los pedidos en curso del turno sigan cabiendo en
 * las horas nuevas.
 */
class TurnoRequest extends FormRequest
{
    /**
     * Ya ha pasado por «auth:personal»: cualquier persona del personal gestiona el horario.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fecha (solo en el alta) y horas en formato HH:MM, con el cierre después de la apertura.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        $reglas = [
            'hora_apertura' => ['required', ...ReglasDeDatos::hora()],
            'hora_cierre' => ['required', ...ReglasDeDatos::hora(), 'after:hora_apertura'],
        ];

        if (! $this->turno() instanceof Horario) {
            $reglas['dia'] = ['required', 'date_format:Y-m-d', 'after_or_equal:today'];
        }

        return $reglas;
    }

    /**
     * Comprobaciones que necesitan la base de datos: solapes y pedidos del turno.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->isNotEmpty()) {
                return;
            }

            $this->comprobarSolapes($validador);
            $this->comprobarPedidos($validador);
        }];
    }

    /**
     * Mensajes con las palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'dia.after_or_equal' => 'No se pueden abrir turnos en una fecha que ya ha pasado.',
            'hora_cierre.after' => 'El cierre tiene que ser posterior a la apertura; un turno no puede pasar de medianoche.',
            '*.date_format' => 'Escribe la hora como HH:MM, por ejemplo 20:30.',
            'dia.date_format' => 'Elige una fecha del calendario.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dia' => 'fecha', 'hora_apertura' => 'apertura', 'hora_cierre' => 'cierre'];
    }

    /**
     * Turno que se está editando, o null en el alta.
     *
     * @return Horario|null
     */
    public function turno(): ?Horario
    {
        $turno = $this->route('turno');

        return $turno instanceof Horario ? $turno : null;
    }

    /**
     * Fecha del turno: la del formulario en el alta y la suya al editar.
     *
     * @return string fecha en formato Y-m-d
     */
    public function dia(): string
    {
        return $this->turno()?->dia->toDateString() ?? (string) $this->input('dia');
    }

    /**
     * Si las horas pisan otro turno de la misma fecha, lo dice junto a la apertura.
     *
     * @param  Validator  $validador  validador del formulario
     * @return void
     */
    private function comprobarSolapes(Validator $validador): void
    {
        $apertura = $this->input('hora_apertura');
        $cierre = $this->input('hora_cierre');

        // un día tiene pocos turnos: se traen todos y se mira cuál pisa las horas nuevas
        $otro = Horario::whereDate('dia', $this->dia())
            ->when($this->turno(), fn ($consulta, Horario $turno) => $consulta->whereKeyNot($turno->id))
            ->orderBy('hora_apertura')
            ->get()
            ->first(fn (Horario $turno) => $turno->seSolapaCon($apertura, $cierre));

        if ($otro) {
            $validador->errors()->add('hora_apertura', sprintf(
                'Se solapa con el turno %d de ese día (%s a %s).',
                $otro->turno, substr($otro->hora_apertura, 0, 5), substr($otro->hora_cierre, 0, 5)
            ));
        }
    }

    /**
     * Al editar, cada pedido aceptado o listo para recoger tiene que seguir
     * dentro de las horas nuevas.
     *
     * @param  Validator  $validador  validador del formulario
     * @return void
     */
    private function comprobarPedidos(Validator $validador): void
    {
        $turno = $this->turno();
        if (! $turno) {
            return;
        }

        $fuera = $turno->pedidos()
            ->whereIn('estado', ['aceptado', 'listo'])
            ->where(fn ($consulta) => $consulta
                ->whereTime('hora_recogida', '<', $this->input('hora_apertura').':00')
                ->orWhereTime('hora_recogida', '>', $this->input('hora_cierre').':00'))
            ->orderBy('hora_recogida')
            ->first();

        if ($fuera instanceof Pedido) {
            $validador->errors()->add('hora_cierre', sprintf(
                'El pedido %s se recoge a las %s, fuera de esas horas. Cambia antes su hora de recogida o deja un horario que la incluya.',
                $fuera->codigo, $fuera->hora_recogida->format('H:i')
            ));
        }
    }
}
