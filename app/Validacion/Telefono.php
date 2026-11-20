<?php

/**
 * Paso de un teléfono escrito a mano al formato E.164 con el que se guarda.
 */

namespace App\Validacion;

/**
 * La gente escribe el teléfono con espacios, puntos, guiones o paréntesis,
 * y casi siempre sin prefijo si es español. Aquí se quitan esos separadores
 * y, si quedan nueve cifras sin «+», se añade +34. Lo que no encaja se
 * devuelve igual de limpio para que la regla de ReglasDeDatos::telefono() lo
 * rechace con su mensaje.
 */
final class Telefono
{
    /**
     * Teléfono sin separadores y, si es español sin prefijo, con +34.
     *
     * @param  string  $escrito  teléfono tal como llega del formulario
     * @return string
     */
    public static function normalizar(string $escrito): string
    {
        $limpio = preg_replace('/[\s.\-()]/', '', $escrito) ?? '';

        return preg_match('/^[1-9]\d{8}$/', $limpio) ? '+34'.$limpio : $limpio;
    }
}
