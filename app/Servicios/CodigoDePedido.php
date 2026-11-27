<?php

/**
 * Código de seguimiento de 8 caracteres de un pedido (RF-04).
 */

namespace App\Servicios;

use Carbon\CarbonInterface;
use Random\RandomException;

/**
 * Seis hexadecimales en mayúsculas sorteados con random_bytes y las dos
 * últimas cifras del año de la fecha prevista, por ejemplo 7B3E0A26. Lo
 * genera PHP y no el disparador para poder calcular el enlace de seguimiento
 * antes de guardar el pedido; si el código ya existe, el
 * registro del pedido pide otro.
 */
class CodigoDePedido
{
    /**
     * Sortea tres bytes con el generador seguro del sistema y les añade el
     * año de la fecha prevista. No comprueba si el código ya existe: eso lo
     * resuelve el índice único al guardar.
     *
     * @param  CarbonInterface  $fechaPrevista  día de recogida previsto; da el año
     * @return string ocho caracteres, como 7B3E0A26
     *
     * @throws RandomException si el sistema no tiene una fuente de aleatoriedad segura
     */
    public function nuevo(CarbonInterface $fechaPrevista): string
    {
        return strtoupper(bin2hex(random_bytes(3))).$fechaPrevista->format('y');
    }
}
