<?php

/**
 * Pruebas de cuándo está abierto el local, según el calendario (RF-02).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Servicios\Calendario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El calendario de las pruebas empieza un martes, el 24/11/2026, con turno
 * partido (13:00–16:00 y 20:00–23:30), el miércoles cerrado y el jueves solo
 * de noche. Cada prueba pregunta por un momento distinto de esa semana.
 */
class CalendarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Servicio que se prueba.
     *
     * @var Calendario
     */
    private Calendario $calendario;

    /**
     * Crea los turnos de la semana de las pruebas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->calendario = new Calendario;

        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '13:00', 'hora_cierre' => '16:00']);
        Horario::create(['dia' => '2026-11-24', 'turno' => 2, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        Horario::create(['dia' => '2026-11-26', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
    }

    /**
     * Momento de la semana de las pruebas.
     *
     * @param  string  $cuando  fecha y hora «Y-m-d H:i»
     * @return Carbon
     */
    private function a(string $cuando): Carbon
    {
        return Carbon::parse($cuando);
    }

    /**
     * En mitad del turno de noche: abierto, y el pedido es para hoy.
     *
     * @return void
     */
    public function test_abierto_en_mitad_del_turno(): void
    {
        $ahora = $this->a('2026-11-24 21:00');

        $this->assertSame(2, $this->calendario->turnoAbierto($ahora)?->turno);
        $this->assertSame('2026-11-24', $this->calendario->fechaPrevista($ahora)?->toDateString());
        $this->assertSame('2026-11-26', $this->calendario->siguienteApertura($ahora)?->dia->toDateString());
    }

    /**
     * Entre los dos turnos del turno partido: cerrado, abre a las 20:00 y el pedido sigue siendo para hoy.
     *
     * @return void
     */
    public function test_entre_los_dos_turnos_del_turno_partido(): void
    {
        $ahora = $this->a('2026-11-24 17:30');

        $this->assertNull($this->calendario->turnoAbierto($ahora));
        $this->assertSame('20:00:00', $this->calendario->siguienteApertura($ahora)?->hora_apertura);
        $this->assertSame('2026-11-24', $this->calendario->fechaPrevista($ahora)?->toDateString());
    }

    /**
     * La hora de cierre ya es cerrado; el pedido pasa al jueves, saltando el miércoles cerrado.
     *
     * @return void
     */
    public function test_al_cerrar_salta_al_siguiente_dia_con_turno(): void
    {
        $ahora = $this->a('2026-11-24 23:30');

        $this->assertNull($this->calendario->turnoAbierto($ahora));
        $this->assertSame('2026-11-26', $this->calendario->fechaPrevista($ahora)?->toDateString());
        $this->assertSame('2026-11-26', $this->calendario->siguienteApertura($ahora)?->dia->toDateString());
    }

    /**
     * Pasada la medianoche del miércoles, sigue tocando el jueves; el jueves de madrugada, también.
     *
     * @return void
     */
    public function test_el_cambio_de_dia_a_medianoche(): void
    {
        $this->assertSame('2026-11-26', $this->calendario->fechaPrevista($this->a('2026-11-25 00:00'))?->toDateString());
        $this->assertSame('2026-11-26', $this->calendario->fechaPrevista($this->a('2026-11-26 00:30'))?->toDateString());
    }

    /**
     * Después del último turno del calendario no hay ninguna apertura ni fecha prevista.
     *
     * @return void
     */
    public function test_sin_aperturas_previstas(): void
    {
        $ahora = $this->a('2026-11-27 10:00');

        $this->assertNull($this->calendario->siguienteApertura($ahora));
        $this->assertNull($this->calendario->fechaPrevista($ahora));
    }

    /**
     * Los próximos siete días salen todos, con los vacíos como cerrados.
     *
     * @return void
     */
    public function test_los_proximos_dias(): void
    {
        $dias = $this->calendario->proximosDias(7, $this->a('2026-11-24 09:00'));

        $this->assertSame(['2026-11-24', '2026-11-25', '2026-11-26', '2026-11-27', '2026-11-28', '2026-11-29', '2026-11-30'], $dias->keys()->all());
        $this->assertSame([2, 0, 1], [$dias['2026-11-24']->count(), $dias['2026-11-25']->count(), $dias['2026-11-26']->count()]);
    }

    /**
     * El inicio dice si está abierto o cuándo abre, y enseña el horario.
     *
     * @return void
     */
    public function test_el_inicio_muestra_el_estado_y_el_horario(): void
    {
        $this->travelTo($this->a('2026-11-24 21:00'));
        $this->get('/')->assertOk()
            ->assertSee('Ahora abierto, hasta las 23:30.')
            ->assertSeeInOrder(['Hoy', '13:00–16:00', '20:00–23:30', 'Cerrado']);

        $this->travelTo($this->a('2026-11-24 23:45'));
        $this->get('/')->assertSee('Ahora cerrado. Abrimos el jueves 26 de noviembre a las 20:00. Puedes pedir ya');

        $this->travelTo($this->a('2026-11-25 12:00'));
        $this->get('/')->assertSee('Abrimos mañana a las 20:00');
    }

    /**
     * Sin aperturas, el inicio pide llamar al teléfono del local.
     *
     * @return void
     */
    public function test_el_inicio_sin_aperturas_pide_llamar(): void
    {
        DB::table('ajustes')->insert(['clave' => 'telefono_local', 'valor' => '+34100000000', 'publico' => true, 'descripcion' => 'Teléfono']);
        $this->travelTo($this->a('2026-11-27 10:00'));

        $this->get('/')
            ->assertSee('Ahora no tenemos fechas de apertura previstas')
            ->assertSee('href="tel:+34100000000"', false);
    }
}
