<?php

/**
 * Modelo de las secciones de la carta (tabla categorias).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sección de la carta: Pizzas, Focaccias, Entrantes, Postres o Bebidas. El
 * orden fija dónde sale en la carta y «activa» permite ocultarla sin borrarla
 * (RF-15). Una categoría con productos no se puede borrar.
 */
class Categoria extends Model
{
    /**
     * Las secciones de la carta viven en categorias.
     *
     * @var string
     */
    protected $table = 'categorias';

    /**
     * La tabla no lleva fechas de alta ni de cambio.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos que se rellenan desde el formulario de la gestión.
     *
     * @var list<string>
     */
    protected $fillable = ['nombre', 'orden', 'activa'];

    /**
     * El orden como número y «activa» como verdadero o falso.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['orden' => 'integer', 'activa' => 'boolean'];
    }

    /**
     * Productos de la sección.
     *
     * @return HasMany<Producto, $this>
     */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }
}
