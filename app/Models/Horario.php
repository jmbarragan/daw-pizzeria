<?php

/**
 * Modelo del calendario de apertura (tabla horarios).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un turno de apertura de una fecha concreta (RF-19). Dos turnos el mismo día
 * forman un turno partido y una fecha sin turnos está cerrada. Que no se
 * solapen lo garantiza un disparador de la base de datos.
 */
class Horario extends Model
{
    /**
     * Un turno por fila: el calendario de apertura.
     *
     * @var string
     */
    protected $table = 'horarios';

    /**
     * El calendario no guarda fechas de alta ni de cambio.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos del formulario de horario (G-07).
     *
     * @var list<string>
     */
    protected $fillable = ['dia', 'turno', 'hora_apertura', 'hora_cierre'];

    /**
     * Conversiones de tipo; las horas se quedan como texto «HH:MM:SS».
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['dia' => 'date', 'turno' => 'integer'];
    }

    /**
     * Pedidos con la recogida en este turno.
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * Indica si unas horas pisan las de este turno. Que una empiece justo
     * cuando acaba la otra no es pisarse: 13:00–16:00 y 16:00–18:00 conviven.
     * Es la misma condición que comprueba el disparador de la base de datos.
     *
     * @param  string  $apertura  hora HH:MM o HH:MM:SS
     * @param  string  $cierre  hora HH:MM o HH:MM:SS
     * @return bool
     */
    public function seSolapaCon(string $apertura, string $cierre): bool
    {
        $propia = [substr($this->hora_apertura, 0, 5), substr($this->hora_cierre, 0, 5)];

        return substr($apertura, 0, 5) < $propia[1] && substr($cierre, 0, 5) > $propia[0];
    }
}
