<?php

/**
 * Pruebas de las transiciones de estado de un pedido aceptado (RF-13, CU-12).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Aceptado → listo → recogido (con pago) o no recogido, y nada más. Las
 * pruebas pasan el martes 24/11/2026 a las 21:00, con la conexión «gestion».
 */
class TransicionesDePedidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Reloj, conexión y empleada identificada.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 21:00'));
        DB::setDefaultConnection('gestion');

        $this->actingAs(Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * Guarda el pedido E07C4A26 en el estado indicado, con los campos que pide cada estado.
     *
     * @param  string  $estado  pendiente, aceptado o listo
     * @return Pedido
     */
    private function pedido(string $estado): Pedido
    {
        $turno = Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Lucía Fernández', 'telefono' => '+34100111222']);
        $datos = [
            'codigo' => 'E07C4A26',
            'cliente_id' => $cliente,
            'token_seguimiento' => hash('sha256', 'E07C4A26'),
            'fecha_prevista' => '2026-11-24',
            'estado' => $estado,
        ];

        if ($estado !== 'pendiente') {
            $datos += [
                'usuario_id' => auth('personal')->id(),
                'decidido_en' => '2026-11-24 20:30:00',
                'horario_id' => $turno->id,
                'hora_recogida' => '2026-11-24 21:45:00',
            ];
        }

        return Pedido::findOrFail(DB::table('pedidos')->insertGetId($datos));
    }

    /**
     * Cambia de estado desde el detalle.
     *
     * @param  string  $estado  estado al que se quiere pasar
     * @return TestResponse
     */
    private function pasarA(string $estado): TestResponse
    {
        return $this->post('/gestion/pedidos/E07C4A26/estado', ['estado' => $estado]);
    }

    /**
     * El camino completo: aceptado → listo → recogido, con el pago y el cierre apuntados.
     *
     * @return void
     */
    public function test_de_aceptado_a_recogido_con_pago(): void
    {
        $pedido = $this->pedido('aceptado');

        // los botones, no la ayuda, que nombra todos los pasos
        $this->get('/gestion/pedidos/E07C4A26')
            ->assertSee('>Listo para recoger</button>', false)
            ->assertDontSee('>Recogido y pagado</button>', false);
        $this->pasarA('listo')->assertSessionHas('estado', 'Pedido E07C4A26 listo para recoger.');
        $this->assertSame(['listo', null, null], [$pedido->refresh()->estado, $pedido->pagado_en, $pedido->cerrado_en]);

        $this->get('/gestion/pedidos/E07C4A26')
            ->assertSee('>Recogido y pagado</button>', false)
            ->assertSee('>No recogido</button>', false);
        $this->pasarA('recogido')->assertSessionHas('estado', 'Pedido E07C4A26 recogido; el pago queda apuntado.');

        $pedido->refresh();
        $this->assertSame('recogido', $pedido->estado);
        $this->assertSame('2026-11-24 21:00', $pedido->pagado_en->format('Y-m-d H:i'));
        $this->assertNotNull($pedido->cerrado_en);
        $this->get('/gestion/pedidos/E07C4A26')->assertDontSee('Siguiente paso');
    }

    /**
     * Si el cliente no viene, se cierra sin pago desde «listo».
     *
     * @return void
     */
    public function test_listo_a_no_recogido_sin_pago(): void
    {
        $pedido = $this->pedido('listo');

        $this->pasarA('no_recogido')->assertSessionHas('estado', 'Pedido E07C4A26 cerrado como no recogido.');

        $pedido->refresh();
        $this->assertSame(['no_recogido', null], [$pedido->estado, $pedido->pagado_en]);
        $this->assertNotNull($pedido->cerrado_en);
    }

    /**
     * Un pendiente no avanza por aquí: primero hay que aceptarlo con su hora.
     *
     * @return void
     */
    public function test_un_pendiente_no_avanza(): void
    {
        $this->pedido('pendiente');

        $this->pasarA('recogido')->assertSessionHas('error', 'No se puede pasar de «Pendiente» a «Recogido».');
        $this->pasarA('listo')->assertSessionHas('error', 'No se puede pasar de «Pendiente» a «Listo».');

        $this->assertSame('pendiente', Pedido::where('codigo', 'E07C4A26')->value('estado'));
    }

    /**
     * Un aceptado solo puede pasar a listo; saltarse pasos o cancelarlo desde aquí se rechaza.
     *
     * @return void
     */
    public function test_un_aceptado_no_se_salta_pasos(): void
    {
        $this->pedido('aceptado');

        $this->pasarA('recogido')->assertSessionHas('error', 'No se puede pasar de «Aceptado» a «Recogido».');
        $this->pasarA('no_recogido')->assertSessionHas('error', 'No se puede pasar de «Aceptado» a «No recogido».');
        $this->pasarA('cancelado')->assertSessionHas('error', 'No se puede pasar de «Aceptado» a «Cancelado».');

        $this->assertSame('aceptado', Pedido::where('codigo', 'E07C4A26')->value('estado'));
    }

    /**
     * Un pedido cerrado no admite ningún paso más.
     *
     * @return void
     */
    public function test_un_pedido_cerrado_no_avanza(): void
    {
        $this->pedido('listo');
        $this->pasarA('recogido');

        $this->pasarA('no_recogido')->assertSessionHas('error', 'No se puede pasar de «Recogido» a «No recogido».');
        $this->assertSame('recogido', Pedido::where('codigo', 'E07C4A26')->value('estado'));
    }
}
