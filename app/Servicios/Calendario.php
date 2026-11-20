<?php

/**
 * Cuándo está abierta la pizzería, según el calendario de turnos (RF-02).
 */

namespace App\Servicios;

use App\Models\Horario;
use Carbon\Carbon as Fecha;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Responde a cuatro preguntas que se hacen en varias pantallas: si está
 * abierto ahora, cuándo abre la próxima vez, qué fecha prevista lleva un
 * pedido que se haga ahora y qué turnos hay en los próximos días. Todo va en
 * la hora de Europe/Madrid (config/app.php) y con la conexión que esté en
 * uso, «web» en la zona pública.
 *
 * Cada método recibe opcionalmente el momento que se consulta; si no, usa el
 * actual. Así las pruebas pueden preguntar por cualquier hora sin mover el reloj.
 */
class Calendario
{
    /**
     * Turno abierto en este momento, si lo hay.
     *
     * @param  Carbon|null  $ahora  momento consultado; por defecto, ahora
     * @return Horario|null
     */
    public function turnoAbierto(?Carbon $ahora = null): ?Horario
    {
        $ahora ??= now();

        return Horario::whereDate('dia', $ahora->toDateString())
            ->where('hora_apertura', '<=', $ahora->format('H:i:s'))
            ->where('hora_cierre', '>', $ahora->format('H:i:s'))
            ->first();
    }

    /**
     * Próximo turno que todavía no ha empezado: más tarde hoy o en otra fecha.
     *
     * @param  Carbon|null  $ahora  momento consultado; por defecto, ahora
     * @return Horario|null null si el calendario no tiene más aperturas
     */
    public function siguienteApertura(?Carbon $ahora = null): ?Horario
    {
        $ahora ??= now();

        return $this->desde($ahora, 'hora_apertura')->first();
    }

    /**
     * Fecha prevista de un pedido hecho ahora (decisión 028): hoy si queda un
     * turno por terminar y, si no, el primer día con turno.
     *
     * @param  Carbon|null  $ahora  momento consultado; por defecto, ahora
     * @return Fecha|null null si no hay ninguna apertura prevista
     */
    public function fechaPrevista(?Carbon $ahora = null): ?Fecha
    {
        $ahora ??= now();

        return $this->desde($ahora, 'hora_cierre')->first()?->dia;
    }

    /**
     * Turnos de los próximos días, de hoy en adelante, con una entrada por
     * fecha aunque esté vacía (cerrado).
     *
     * @param  int  $dias  cuántos días se enseñan
     * @param  Carbon|null  $ahora  momento consultado; por defecto, ahora
     * @return Collection<string, Collection<int, Horario>> fecha Y-m-d => turnos de ese día
     */
    public function proximosDias(int $dias = 7, ?Carbon $ahora = null): Collection
    {
        $hoy = ($ahora ?? now())->copy()->startOfDay();

        $turnos = Horario::whereBetween('dia', [$hoy->toDateString(), $hoy->copy()->addDays($dias - 1)->toDateString()])
            ->orderBy('dia')->orderBy('hora_apertura')
            ->get();

        $porDia = [];
        for ($n = 0; $n < $dias; $n++) {
            $fecha = $hoy->copy()->addDays($n)->toDateString();
            $porDia[$fecha] = $turnos->filter(fn (Horario $turno) => $turno->dia->toDateString() === $fecha)->values()->toBase();
        }

        return collect($porDia);
    }

    /**
     * Turnos de hoy cuya hora indicada aún no ha pasado y los de las fechas
     * siguientes, en orden.
     *
     * @param  Carbon  $ahora  momento consultado
     * @param  string  $columna  hora_apertura (que no ha empezado) u hora_cierre (que no ha terminado)
     * @return Builder<Horario>
     */
    private function desde(Carbon $ahora, string $columna): Builder
    {
        $hoy = $ahora->toDateString();

        return Horario::where(fn (Builder $consulta) => $consulta
            ->whereDate('dia', '>', $hoy)
            ->orWhere(fn (Builder $deHoy) => $deHoy->whereDate('dia', $hoy)->where($columna, '>', $ahora->format('H:i:s'))))
            ->orderBy('dia')
            ->orderBy('hora_apertura');
    }
}
