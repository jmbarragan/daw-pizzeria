<?php

/**
 * Lectura de los ajustes que puede ver la zona pública.
 */

namespace App\Servicios;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;

/**
 * La zona pública entra como focaccia_web, que no puede leer la tabla de
 * ajustes (guarda, por ejemplo, las contraseñas del correo); solo la vista
 * v_ajustes_publicos, con los ajustes marcados como públicos: nombre,
 * teléfono y dirección del local, máximo de pizzas y política de privacidad. Con
 * #[Scoped] el contenedor da la misma instancia durante toda la petición, así
 * que la vista se lee una sola vez.
 */
#[Scoped]
class AjustesPublicos
{
    /**
     * Ajustes ya leídos en esta petición.
     *
     * @var array<string, string|null>|null
     */
    private ?array $valores = null;

    /**
     * Valor de un ajuste público.
     *
     * @param  string  $clave  por ejemplo «telefono_local»
     * @param  string|null  $porDefecto  valor si el ajuste no existe
     * @return string|null
     */
    public function valor(string $clave, ?string $porDefecto = null): ?string
    {
        $this->valores ??= DB::table('v_ajustes_publicos')->pluck('valor', 'clave')->all();

        return $this->valores[$clave] ?? $porDefecto;
    }
}
