<?php

/**
 * Pruebas unitarias de cuándo dos turnos del mismo día se solapan (RF-19).
 */

namespace Tests\Unit;

use App\Models\Horario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * El turno guardado es siempre el de la cena, de 20:00 a 23:30, y cada caso
 * prueba unas horas nuevas contra él. No hace falta base de datos: el turno
 * no se guarda.
 */
class SolapesDeTurnosTest extends TestCase
{
    /**
     * Horas nuevas (apertura y cierre) y si pisan el turno de 20:00 a 23:30.
     *
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public static function casos(): array
    {
        return [
            'comida, mucho antes' => ['13:00', '16:00', false],
            'acaba justo cuando abre la cena' => ['16:00', '20:00', false],
            'empieza justo cuando cierra la cena' => ['23:30', '23:59', false],
            'acaba dentro de la cena' => ['19:00', '20:30', true],
            'empieza dentro de la cena' => ['23:00', '23:45', true],
            'dentro de la cena' => ['21:00', '22:00', true],
            'envuelve la cena' => ['19:00', '23:45', true],
            'las mismas horas' => ['20:00', '23:30', true],
            'con segundos, como las guarda MySQL' => ['20:00:00', '21:00:00', true],
        ];
    }

    /**
     * Comprueba cada caso contra el turno de la cena.
     *
     * @param  string  $apertura  apertura de las horas nuevas
     * @param  string  $cierre  cierre de las horas nuevas
     * @param  bool  $esperado  si deben pisar el turno
     * @return void
     */
    #[DataProvider('casos')]
    public function test_solape_con_el_turno_de_la_cena(string $apertura, string $cierre, bool $esperado): void
    {
        $cena = new Horario(['hora_apertura' => '20:00:00', 'hora_cierre' => '23:30:00']);

        $this->assertSame($esperado, $cena->seSolapaCon($apertura, $cierre));
    }
}
