<?php

/**
 * Modelo de los productos de la carta (tabla productos).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pizza, focaccia, entrante, postre o bebida. «disponible» a falso significa
 * agotado: se ve en la carta pero no se puede pedir. Un producto con ventas no
 * se borra; se retira de la carta rellenando retirado_en (RF-16).
 */
class Producto extends Model
{
    /**
     * Fecha de alta.
     *
     * @var string
     */
    const CREATED_AT = 'creado_en';

    /**
     * Fecha del último cambio.
     *
     * @var string
     */
    const UPDATED_AT = 'actualizado_en';

    /**
     * Productos de la carta, incluidos los retirados.
     *
     * @var string
     */
    protected $table = 'productos';

    /**
     * Campos del formulario de producto (G-05).
     *
     * @var list<string>
     */
    protected $fillable = ['categoria_id', 'nombre', 'descripcion', 'precio', 'disponible', 'retirado_en'];

    /**
     * Conversiones de tipo. El precio se maneja como texto con dos decimales
     * para no perder céntimos al pasar por coma flotante.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'disponible' => 'boolean',
            'retirado_en' => 'datetime',
        ];
    }

    /**
     * Sección de la carta a la que pertenece.
     *
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * Alérgenos declarados, a través de productos_alergenos.
     *
     * @return BelongsToMany<Alergeno, $this>
     */
    public function alergenos(): BelongsToMany
    {
        return $this->belongsToMany(Alergeno::class, 'productos_alergenos');
    }

    /**
     * Líneas de pedido en las que se ha vendido.
     *
     * @return HasMany<LineaPedido, $this>
     */
    public function lineas(): HasMany
    {
        return $this->hasMany(LineaPedido::class);
    }

    /**
     * Solo los productos que siguen en la carta (sin fecha de retirada).
     *
     * @param  Builder<Producto>  $consulta  consulta a la que se añade la condición
     * @return void
     */
    public function scopeEnCarta(Builder $consulta): void
    {
        $consulta->whereNull('retirado_en');
    }
}
