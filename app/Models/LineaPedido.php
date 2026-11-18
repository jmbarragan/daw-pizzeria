<?php

/**
 * Modelo de las líneas de cada pedido (tabla lineas_pedido).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Producto pedido con su cantidad (de 1 a 20, RF-03) y el nombre y el precio
 * que tenía al venderse, para que el pedido no cambie si cambia la carta.
 *
 * La clave es compuesta (pedido_id, producto_id), que Eloquent no gestiona:
 * las líneas se crean y se leen a través de su pedido y no se guardan sueltas.
 */
class LineaPedido extends Model
{
    /**
     * Laravel la buscaría como linea_pedidos; en el diseño es lineas_pedido.
     *
     * @var string
     */
    protected $table = 'lineas_pedido';

    /**
     * Sin clave autoincremental: la clave es el par pedido y producto.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Las líneas no llevan fechas propias.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos de la línea al crear el pedido.
     *
     * @var list<string>
     */
    protected $fillable = ['pedido_id', 'producto_id', 'nombre_producto', 'precio_unitario', 'cantidad'];

    /**
     * Precio con dos decimales exactos y cantidad entera de 1 a 20.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['precio_unitario' => 'decimal:2', 'cantidad' => 'integer'];
    }

    /**
     * Pedido al que pertenece.
     *
     * @return BelongsTo<Pedido, $this>
     */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class);
    }

    /**
     * Producto de la carta, aunque después se haya retirado.
     *
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
