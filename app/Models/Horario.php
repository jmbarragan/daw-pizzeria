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
}
