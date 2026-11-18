<?php

/**
 * Traslada las fechas fijas de los datos de prueba del diseño a la fecha de instalación.
 */

namespace Database\Seeders;

use Illuminate\Support\Carbon;

/**
 * Los datos de prueba del diseño ocurren la semana del 03/11/2026 al
 * 09/11/2026, con el pedido pendiente el viernes 06/11. Instalados más tarde,
 * todo quedaría en el pasado y el local saldría siempre cerrado. Aquí se
 * desplazan todas las fechas los mismos días, de forma que ese viernes pasa a
 * ser el día de la instalación y se conservan las distancias entre fechas.
 */
class CalendarioDePrueba
{
    /**
     * Día del diseño que se hace coincidir con el día de la instalación.
     *
     * @var string
     */
    private const DIA_DE_REFERENCIA = '2026-11-06';

    /**
     * Días que hay que sumar a cada fecha del diseño.
     *
     * @var int
     */
    private int $dias;

    /**
     * Calcula el desplazamiento respecto a hoy.
     */
    public function __construct()
    {
        $this->dias = (int) Carbon::parse(self::DIA_DE_REFERENCIA)->diffInDays(Carbon::today(), false);
    }

    /**
     * Fecha y hora desplazadas, en el formato de MySQL.
     *
     * @param  string  $fechaDelDiseno  por ejemplo «2026-11-03 20:05:00»
     * @return string
     */
    public function momento(string $fechaDelDiseno): string
    {
        return Carbon::parse($fechaDelDiseno)->addDays($this->dias)->format('Y-m-d H:i:s');
    }

    /**
     * Solo la fecha, desplazada.
     *
     * @param  string  $diaDelDiseno  por ejemplo «2026-11-03»
     * @return string
     */
    public function dia(string $diaDelDiseno): string
    {
        return Carbon::parse($diaDelDiseno)->addDays($this->dias)->format('Y-m-d');
    }

    /**
     * Código del pedido: los 6 hexadecimales del diseño y el año de la fecha
     * de recogida ya desplazada, que es lo que exige ck_pedidos_codigo.
     *
     * @param  string  $codigoDelDiseno  por ejemplo «7B3E0A26»
     * @param  string  $fechaPrevista  fecha de recogida ya desplazada
     * @return string
     */
    public function codigo(string $codigoDelDiseno, string $fechaPrevista): string
    {
        return substr($codigoDelDiseno, 0, 6).Carbon::parse($fechaPrevista)->format('y');
    }
}
