<?php

/**
 * Modelo de los ajustes del local (tabla ajustes).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ajuste que el administrador cambia sin tocar el código (G-10): política de
 * privacidad, teléfono del local, datos del servidor de correo... Los marcados
 * como públicos los lee la web a través de la vista v_ajustes_publicos.
 */
class Ajuste extends Model
{
    /**
     * La tabla no guarda fecha de alta.
     *
     * @var null
     */
    const CREATED_AT = null;

    /**
     * Fecha del último cambio.
     *
     * @var string
     */
    const UPDATED_AT = 'actualizado_en';

    /**
     * Ajustes del local, uno por clave.
     *
     * @var string
     */
    protected $table = 'ajustes';

    /**
     * La clave del ajuste es su identificador.
     *
     * @var string
     */
    protected $primaryKey = 'clave';

    /**
     * La clave es texto, no un número autoincremental.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Tipo de la clave primaria.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Campos que cambia el administrador.
     *
     * @var list<string>
     */
    protected $fillable = ['clave', 'valor', 'publico', 'descripcion', 'actualizado_por'];

    /**
     * «publico» decide si la vista v_ajustes_publicos lo enseña a la web.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['publico' => 'boolean'];
    }

    /**
     * Administrador que hizo el último cambio.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'actualizado_por');
    }
}
