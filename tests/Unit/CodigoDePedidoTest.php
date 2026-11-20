<?php

/**
 * Pruebas unitarias del sorteo del código del pedido (RF-04).
 */

namespace Tests\Unit;

use App\Servicios\CodigoDePedido;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * El código son seis hexadecimales en mayúsculas al azar y las dos últimas
 * cifras del año de la fecha prevista, no del año en que se hace el pedido.
 */
class CodigoDePedidoTest extends TestCase
{
    /**
     * Mil sorteos cumplen el formato y casi nunca se repiten.
     *
     * @return void
     */
    public function test_formato_y_azar(): void
    {
        $codigos = new CodigoDePedido;
        $sorteos = [];

        for ($i = 0; $i < 1000; $i++) {
            $sorteos[] = $codigos->nuevo(Carbon::parse('2026-11-24'));
        }

        foreach ($sorteos as $codigo) {
            $this->assertMatchesRegularExpression('/^[0-9A-F]{6}26$/', $codigo);
        }

        // 16,7 millones de posibilidades: en mil sorteos, repetir más de dos sería muy raro
        $this->assertGreaterThan(997, count(array_unique($sorteos)));
    }

    /**
     * Un pedido hecho el 31 de diciembre para recoger el 2 de enero lleva el año nuevo.
     *
     * @return void
     */
    public function test_el_ano_es_el_de_la_fecha_prevista(): void
    {
        $codigo = (new CodigoDePedido)->nuevo(Carbon::parse('2027-01-02'));

        $this->assertStringEndsWith('27', $codigo);
        $this->assertSame(8, strlen($codigo));
    }
}
