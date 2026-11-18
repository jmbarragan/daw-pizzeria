<?php

/**
 * Modelo de los bloqueos de IP (tabla bloqueos_ip).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloqueo de una IP tras demasiados accesos fallidos (RNF-01). La base de
 * datos calcula el fin: 24 horas después, o ninguno (permanente) si la misma IP
 * ya se bloqueó en los 30 días anteriores. Solo un administrador lo levanta.
 */
class BloqueoIp extends Model
{
    /**
     * Laravel la buscaría como bloqueo_ips; en el diseño es bloqueos_ip.
     *
     * @var string
     */
    protected $table = 'bloqueos_ip';

    /**
     * Las fechas del bloqueo son inicio, fin y desbloqueado_en, no las de Laravel.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Campos que se rellenan al bloquear y al desbloquear.
     *
     * @var list<string>
     */
    protected $fillable = ['ip', 'inicio', 'desbloqueado_por', 'desbloqueado_en'];

    /**
     * Conversiones de tipo de las fechas.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['inicio' => 'datetime', 'fin' => 'datetime', 'desbloqueado_en' => 'datetime'];
    }

    /**
     * Administrador que levantó el bloqueo.
     *
     * @return BelongsTo<Usuario, $this>
     */
    public function desbloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'desbloqueado_por');
    }

    /**
     * Bloqueos que siguen en vigor: sin levantar y sin fin o con el fin aún por llegar.
     *
     * @param  Builder<BloqueoIp>  $consulta  consulta a la que se añaden las condiciones
     * @return void
     */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->whereNull('desbloqueado_en')
            ->where(fn (Builder $fin) => $fin->whereNull('fin')->orWhere('fin', '>', now()));
    }
}
