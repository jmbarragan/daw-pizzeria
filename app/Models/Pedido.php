<?php

/**
 * Modelo de los pedidos para recoger (tabla pedidos).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pedido de un cliente, hecho en la web o por teléfono. Nace pendiente y
 * termina recogido, rechazado, cancelado o no recogido (RF-12, RF-13). El
 * código de 8 caracteres lo pone la base de datos si no viene, y las reglas de
 * qué datos exige cada estado están en restricciones CHECK.
 */
class Pedido extends Model
{
    /**
     * Estados posibles, en el orden del ENUM de la columna: el camino normal
     * (pendiente, aceptado, listo, recogido) y los otros tres finales.
     *
     * @var list<string>
     */
    const ESTADOS = ['pendiente', 'aceptado', 'listo', 'recogido', 'rechazado', 'cancelado', 'no_recogido'];

    /**
     * Estados que cierran el pedido.
     *
     * @var list<string>
     */
    const ESTADOS_FINALES = ['recogido', 'rechazado', 'cancelado', 'no_recogido'];

    /**
     * Columna con el momento en que se hizo el pedido; sustituye a la
     * created_at de Laravel.
     *
     * @var string
     */
    const CREATED_AT = 'creado_en';

    /**
     * El pedido no guarda una fecha de último cambio: cada paso tiene la suya.
     *
     * @var null
     */
    const UPDATED_AT = null;

    /**
     * Coincide con la que deduciría Laravel; se escribe para que se vea junto
     * a las otras tablas, cuyos nombres no siguen la regla del inglés.
     *
     * @var string
     */
    protected $table = 'pedidos';

    /**
     * Campos que se asignan al crear o al cambiar de estado.
     *
     * @var list<string>
     */
    protected $fillable = [
        'codigo', 'cliente_id', 'usuario_id', 'horario_id', 'canal', 'estado', 'token_seguimiento',
        'observaciones', 'motivo_rechazo', 'fecha_prevista', 'decidido_en', 'hora_recogida', 'cerrado_en', 'pagado_en',
    ];

    /**
     * El token del enlace de seguimiento no se expone al serializar.
     *
     * @var list<string>
     */
    protected $hidden = ['token_seguimiento'];

    /**
     * Conversiones de tipo de las fechas del pedido.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_prevista' => 'date',
            'decidido_en' => 'datetime',
            'hora_recogida' => 'datetime',
            'cerrado_en' => 'datetime',
            'pagado_en' => 'datetime',
        ];
    }

    /**
     * Cliente que hizo el pedido.
     *
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Empleado que lo aceptó o rechazó; vacío mientras está pendiente.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    /**
     * Turno en el que se recoge; solo existe si se aceptó.
     *
     * @return BelongsTo<Horario, $this>
     */
    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class);
    }

    /**
     * Productos pedidos, con el nombre y el precio del momento de la venta.
     *
     * @return HasMany<LineaPedido, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class);
    }

    /**
     * Importe del pedido: suma de cantidad por precio de sus líneas. Es el
     * mismo cálculo que hace la vista v_pedidos_totales.
     *
     * @return Attribute<numeric-string, never>
     */
    protected function total(): Attribute
    {
        return Attribute::get(fn (): string => number_format(
            $this->lineas->sum(fn (LineaPedido $linea): float => $linea->cantidad * (float) $linea->precio_unitario),
            2, '.', ''
        ));
    }

    /**
     * Un pedido recogido, rechazado, cancelado o no recogido ya no admite
     * cambios de estado: ni el cliente puede cancelarlo ni el personal decidirlo.
     *
     * @return bool
     */
    public function estaCerrado(): bool
    {
        return in_array($this->estado, self::ESTADOS_FINALES, true);
    }
}
