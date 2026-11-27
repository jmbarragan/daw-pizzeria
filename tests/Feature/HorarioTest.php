<?php

/**
 * Pruebas del calendario de apertura (RF-19, CU-16, pantalla G-07).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las fechas se toman a partir de hoy para que las pruebas valgan cualquier
 * día. Los datos se crean en la conexión «gestion», con una empleada.
 */
class HorarioTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Empleada que gestiona el horario y decide los pedidos de las pruebas.
     *
     * @var Usuario
     */
    private Usuario $empleada;

    /**
     * Entra la empleada con la conexión de gestión.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');

        $this->empleada = Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
        $this->actingAs($this->empleada, 'personal');
    }

    /**
     * Fecha a unos días de hoy, en formato Y-m-d.
     *
     * @param  int  $dias  días desde hoy
     * @return string
     */
    private function dentroDe(int $dias): string
    {
        return today()->addDays($dias)->toDateString();
    }

    /**
     * Turno ya guardado.
     *
     * @param  string  $dia  fecha Y-m-d
     * @param  int  $numero  número de turno del día
     * @param  string  $apertura  hora HH:MM
     * @param  string  $cierre  hora HH:MM
     * @return Horario
     */
    private function turno(string $dia, int $numero, string $apertura, string $cierre): Horario
    {
        return Horario::create(['dia' => $dia, 'turno' => $numero, 'hora_apertura' => $apertura, 'hora_cierre' => $cierre]);
    }

    /**
     * Un pedido aceptado que se recoge en el turno a esa hora.
     *
     * @param  Horario  $turno  turno de la recogida
     * @param  string  $hora  hora de recogida HH:MM
     * @return string código que le pone la base de datos
     */
    private function pedidoAceptado(Horario $turno, string $hora): string
    {
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Carla Pons', 'telefono' => '+34100000001']);
        $dia = $turno->dia->toDateString();

        $pedido = DB::table('pedidos')->insertGetId([
            'cliente_id' => $cliente,
            'token_seguimiento' => hash('sha256', $dia.$hora),
            'fecha_prevista' => $dia,
            'estado' => 'aceptado',
            'usuario_id' => $this->empleada->id,
            'horario_id' => $turno->id,
            'decidido_en' => now(),
            'hora_recogida' => "{$dia} {$hora}:00",
        ]);

        return DB::table('pedidos')->where('id', $pedido)->value('codigo');
    }

    /**
     * El calendario enseña 14 días: los que no tienen turnos salen cerrados y
     * el turno partido ocupa dos filas.
     *
     * @return void
     */
    public function test_calendario_con_turno_partido_y_dias_cerrados(): void
    {
        $sabado = $this->dentroDe(2);
        $this->turno($sabado, 1, '13:00', '16:00');
        $this->turno($sabado, 2, '20:00', '23:30');

        $respuesta = $this->get("/gestion/horario?desde={$this->dentroDe(0)}")->assertOk();

        $respuesta->assertSeeInOrder(['13:00', '16:00', '20:00', '23:30']);
        $this->assertSame(13, substr_count($respuesta->getContent(), '>Cerrado<'));
    }

    /**
     * El alta pone el siguiente número de turno de la fecha.
     *
     * @return void
     */
    public function test_alta_numera_los_turnos_del_dia(): void
    {
        $dia = $this->dentroDe(3);

        $this->post('/gestion/horario/turnos', ['dia' => $dia, 'hora_apertura' => '13:00', 'hora_cierre' => '16:00'])
            ->assertRedirect("/gestion/horario?desde={$dia}");
        $this->post('/gestion/horario/turnos', ['dia' => $dia, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30'])
            ->assertSessionHas('estado', 'Turno 2 del '.today()->addDays(3)->format('d/m/Y').' añadido.');

        $this->assertSame([1, 2], Horario::whereDate('dia', $dia)->orderBy('turno')->pluck('turno')->all());
    }

    /**
     * Fecha pasada, cierre antes de la apertura y solape con otro turno del día.
     *
     * @return void
     */
    public function test_horas_no_validas(): void
    {
        $dia = $this->dentroDe(4);
        $this->turno($dia, 1, '13:00', '16:00');

        $this->post('/gestion/horario/turnos', ['dia' => $this->dentroDe(-1), 'hora_apertura' => '13:00', 'hora_cierre' => '16:00'])
            ->assertSessionHasErrors(['dia' => 'No se pueden abrir turnos en una fecha que ya ha pasado.']);

        $this->post('/gestion/horario/turnos', ['dia' => $dia, 'hora_apertura' => '23:00', 'hora_cierre' => '01:00'])
            ->assertSessionHasErrors(['hora_cierre' => 'El cierre tiene que ser posterior a la apertura; un turno no puede pasar de medianoche.']);

        $this->post('/gestion/horario/turnos', ['dia' => $dia, 'hora_apertura' => '15:30', 'hora_cierre' => '18:00'])
            ->assertSessionHasErrors(['hora_apertura' => 'Se solapa con el turno 1 de ese día (13:00 a 16:00).']);

        // empezar justo cuando acaba el otro no es un solape
        $this->post('/gestion/horario/turnos', ['dia' => $dia, 'hora_apertura' => '16:00', 'hora_cierre' => '18:00'])
            ->assertSessionHasNoErrors();
    }

    /**
     * Al editar cambian las horas; un solape con otro turno del día se rechaza.
     *
     * @return void
     */
    public function test_editar_las_horas(): void
    {
        $dia = $this->dentroDe(5);
        $comida = $this->turno($dia, 1, '13:00', '16:00');
        $this->turno($dia, 2, '20:00', '23:30');

        $this->put("/gestion/horario/turnos/{$comida->id}", ['hora_apertura' => '12:30', 'hora_cierre' => '16:30'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['12:30:00', '16:30:00'], [$comida->refresh()->hora_apertura, $comida->hora_cierre]);

        $this->put("/gestion/horario/turnos/{$comida->id}", ['hora_apertura' => '12:30', 'hora_cierre' => '21:00'])
            ->assertSessionHasErrors(['hora_apertura' => 'Se solapa con el turno 2 de ese día (20:00 a 23:30).']);
    }

    /**
     * Un turno sin pedidos se borra; con pedidos, no.
     *
     * @return void
     */
    public function test_borrar_un_turno(): void
    {
        $vacio = $this->turno($this->dentroDe(6), 1, '20:00', '23:30');
        $conPedido = $this->turno($this->dentroDe(7), 1, '20:00', '23:30');
        $this->pedidoAceptado($conPedido, '21:00');

        $this->delete("/gestion/horario/turnos/{$vacio->id}")->assertSessionHas('estado');
        $this->assertModelMissing($vacio);

        $this->delete("/gestion/horario/turnos/{$conPedido->id}")
            ->assertSessionHas('error', 'No se puede borrar el turno 1 del '.$conPedido->dia->format('d/m/Y')
                .' porque tiene un pedido con la recogida en él. Puedes cambiar sus horas mientras sigan incluyendo las recogidas pendientes.');
        $this->assertModelExists($conPedido);
    }

    /**
     * Con un pedido aceptado para las 21:00, las horas pueden cambiar si lo
     * siguen incluyendo, y no si lo dejan fuera.
     *
     * @return void
     */
    public function test_las_horas_nuevas_tienen_que_incluir_las_recogidas(): void
    {
        $turno = $this->turno($this->dentroDe(8), 1, '20:00', '23:30');
        $codigo = $this->pedidoAceptado($turno, '21:00');

        $this->put("/gestion/horario/turnos/{$turno->id}", ['hora_apertura' => '19:30', 'hora_cierre' => '22:00'])
            ->assertSessionHasNoErrors();

        $this->put("/gestion/horario/turnos/{$turno->id}", ['hora_apertura' => '21:30', 'hora_cierre' => '23:30'])
            ->assertSessionHasErrors(['hora_cierre' => "El pedido {$codigo} se recoge a las 21:00, fuera de esas horas. "
                .'Cambia antes su hora de recogida o deja un horario que la incluya.']);

        $this->assertSame('19:30:00', $turno->refresh()->hora_apertura);
    }
}
