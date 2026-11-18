<?php

/**
 * Modelo del catálogo de alérgenos (tabla alergenos).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Uno de los 14 alérgenos que el Reglamento (UE) 1169/2011 obliga a indicar.
 * La descripción es el texto que ve el cliente en la carta (RF-01, RF-17).
 */
class Alergeno extends Model
{
    /**
     * Catálogo legal de alérgenos.
     *
     * @var string
     */
    protected $table = 'alergenos';

    /**
     * El catálogo no guarda fechas.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos editables desde la gestión del catálogo.
     *
     * @var list<string>
     */
    protected $fillable = ['nombre', 'descripcion'];

    /**
     * Productos que declaran este alérgeno, a través de productos_alergenos.
     *
     * @return BelongsToMany<Producto, $this>
     */
    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'productos_alergenos');
    }
}
