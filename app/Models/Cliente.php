<?php

/**
 * Modelo de los clientes (tabla clientes).
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Cliente que pide para recoger. Sin contraseña es un invitado; con ella, un
 * cliente registrado que puede entrar y ver su historial (RF-08). A los 30
 * días de su último pedido, de un invitado se borran el teléfono y el correo,
 * pero no el nombre (RNF-10).
 */
class Cliente extends Authenticatable
{
    use Notifiable;

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
     * Invitados y registrados comparten la tabla clientes.
     *
     * @var string
     */
    protected $table = 'clientes';

    /**
     * Columna donde se guarda el hash de la contraseña.
     *
     * @var string
     */
    protected $authPasswordName = 'contrasena';

    /**
     * Vacío: no hay «recordarme».
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * Datos que deja el cliente al pedir o al registrarse.
     *
     * @var list<string>
     */
    protected $fillable = ['nombre', 'telefono', 'email', 'contrasena'];

    /**
     * El hash de la contraseña no sale del modelo.
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
            'email_verificado_en' => 'datetime',
            'anonimizado_en' => 'datetime',
        ];
    }

    /**
     * Pedidos del cliente.
     *
     * @return HasMany<Pedido, $this>
     */
    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class);
    }

    /**
     * Un invitado no tiene contraseña.
     *
     * @return bool
     */
    public function esInvitado(): bool
    {
        return $this->contrasena === null;
    }
}
