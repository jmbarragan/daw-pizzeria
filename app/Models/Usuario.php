<?php

/**
 * Modelo del personal de la pizzería (tabla usuarios).
 */

namespace App\Models;

use App\Notifications\RecuperarContrasena;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Empleado o administrador que entra en la zona de gestión (RF-10). El
 * administrador además gestiona al personal y los ajustes (RF-21). Una baja
 * pone «activo» a falso y conserva el historial de pedidos que decidió.
 */
class Usuario extends Authenticatable
{
    use Notifiable;

    /**
     * Rol con acceso a usuarios, ajustes y bloqueos.
     *
     * @var string
     */
    const ROL_ADMINISTRADOR = 'administrador';

    /**
     * Rol del resto del personal.
     *
     * @var string
     */
    const ROL_EMPLEADO = 'empleado';

    /**
     * Fecha de alta.
     *
     * @var string
     */
    const CREATED_AT = 'creado_en';

    /**
     * La tabla no guarda la fecha del último cambio.
     *
     * @var null
     */
    const UPDATED_AT = null;

    /**
     * Personal: usuarios, no users, que es la tabla del esqueleto que se retiró.
     *
     * @var string
     */
    protected $table = 'usuarios';

    /**
     * Columna donde se guarda el hash de la contraseña.
     *
     * @var string
     */
    protected $authPasswordName = 'contrasena';

    /**
     * Vacío: el diseño no tiene remember_token, así que no hay «recordarme».
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * Campos del formulario de usuarios del personal (G-09).
     *
     * @var list<string>
     */
    protected $fillable = ['nombre', 'email', 'contrasena', 'rol', 'activo'];

    /**
     * Nunca se envía el hash fuera del modelo.
     *
     * @var list<string>
     */
    protected $hidden = ['contrasena'];

    /**
     * Conversiones de tipo; la contraseña se guarda siempre con hash.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contrasena' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Pedidos que ha aceptado o rechazado.
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidosDecididos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * Indica si puede entrar en las pantallas de administración.
     *
     * @return bool
     */
    public function esAdministrador(): bool
    {
        return $this->rol === self::ROL_ADMINISTRADOR;
    }

    /**
     * Envía el correo de recuperación propio, en español.
     *
     * @param  string  $token  token del enlace
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RecuperarContrasena($token));
    }
}
