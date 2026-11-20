<?php

/**
 * Pruebas del detalle de un pedido y de su aceptación o rechazo (RF-12, CU-11, G-03).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pruebas pasan el martes 24/11/2026 a las 20:40, con un turno de 20:00 a
 * 23:30, la conexión «gestion» y una empleada identificada.
 */
class DecisionDePedidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Empleada que decide los pedidos.
     *
     * @var Usuario
     */
    private Usuario $empleada;

    /**
     * Reloj, conexión, empleada y turno de la noche.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        DB::setDefaultConnection('gestion');

        $this->empleada = Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
        $this->actingAs($this->empleada, 'personal');

        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
    }

    /**
     * Pedido pendiente de Lucía con dos Margherita, gluten y una observación.
     *
     * @return Pedido
     */
    private function pedidoPendiente(): Pedido
    {
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        $producto = DB::table('productos')->insertGetId(['categoria_id' => $categoria, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $gluten = DB::table('alergenos')->insertGetId(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten']);
        DB::table('productos_alergenos')->insert(['producto_id' => $producto, 'alergeno_id' => $gluten]);
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Lucía Fernández', 'telefono' => '+34100111222']);

        $id = DB::table('pedidos')->insertGetId([
            'codigo' => 'E07C4A26',
            'cliente_id' => $cliente,
            'token_seguimiento' => hash('sha256', 'E07C4A26'),
            'fecha_prevista' => '2026-11-24',
            'observaciones' => 'Bien hecha',
        ]);
        DB::table('lineas_pedido')->insert([
            'pedido_id' => $id,
            'producto_id' => $producto,
            'nombre_producto' => 'Margherita',
            'precio_unitario' => '9.50',
            'cantidad' => 2,
        ]);

        return Pedido::findOrFail($id);
    }

    /**
     * El detalle enseña cliente, líneas, total, alérgenos y observaciones, y
     * solo las franjas que aún no han pasado.
     *
     * @return void
     */
    public function test_el_detalle_y_las_franjas(): void
    {
        $this->pedidoPendiente();

        $this->get('/gestion/pedidos/E07C4A26')
            ->assertOk()
            ->assertSeeInOrder(['Lucía Fernández', '+34100111222', '2 × Margherita', '19,00 €', 'Total', '19,00 €'])
            ->assertSee('Alérgenos:</strong> gluten', false)
            ->assertSee('Bien hecha')
            ->assertSee('<option value="20:45"', false)
            ->assertSee('<option value="23:15"', false)
            ->assertDontSee('<option value="20:30"', false)
            ->assertDontSee('<option value="23:30"', false);
    }

    /**
     * Aceptar guarda la hora, el turno, quién y cuándo, y el pedido sale de los pendientes.
     *
     * @return void
     */
    public function test_aceptar_con_hora(): void
    {
        $pedido = $this->pedidoPendiente();

        $this->post('/gestion/pedidos/E07C4A26/aceptar', ['hora' => '21:45'])
            ->assertRedirect('/gestion/pedidos/E07C4A26')
            ->assertSessionHas('estado', 'Pedido E07C4A26 aceptado para recoger a las 21:45.');

        $pedido->refresh();
        $this->assertSame(['aceptado', '2026-11-24 21:45', $this->empleada->id], [
            $pedido->estado, $pedido->hora_recogida->format('Y-m-d H:i'), $pedido->usuario_id,
        ]);
        $this->assertNotNull($pedido->decidido_en);
        $this->get('/gestion/pedidos')->assertSee('Pendientes (0)');
    }

    /**
     * Una hora que ya ha pasado, que no está en la lista o vacía no se acepta.
     *
     * @return void
     */
    public function test_horas_no_validas(): void
    {
        $this->pedidoPendiente();

        foreach (['20:30', '21:10', '23:30', ''] as $hora) {
            $this->post('/gestion/pedidos/E07C4A26/aceptar', ['hora' => $hora])->assertSessionHasErrors('hora');
        }

        $this->assertSame('pendiente', Pedido::where('codigo', 'E07C4A26')->value('estado'));
    }

    /**
     * Rechazar pide motivo; con él, el pedido se cierra.
     *
     * @return void
     */
    public function test_rechazar_con_motivo(): void
    {
        $pedido = $this->pedidoPendiente();

        $this->post('/gestion/pedidos/E07C4A26/rechazar', ['motivo' => ''])
            ->assertSessionHasErrors(['motivo' => 'Escribe el motivo: el cliente lo recibirá por correo.']);

        $this->post('/gestion/pedidos/E07C4A26/rechazar', ['motivo' => 'Nos hemos quedado sin masa'])
            ->assertSessionHas('estado', 'Pedido E07C4A26 rechazado.');

        $pedido->refresh();
        $this->assertSame(['rechazado', 'Nos hemos quedado sin masa'], [$pedido->estado, $pedido->motivo_rechazo]);
        $this->assertNotNull($pedido->cerrado_en);
    }

    /**
     * Si el cliente cancela mientras la empleada decide, la aceptación no se
     * aplica y se le avisa (CU-11, 4a).
     *
     * @return void
     */
    public function test_cancelado_mientras_se_decide(): void
    {
        $pedido = $this->pedidoPendiente();
        $this->get('/gestion/pedidos/E07C4A26')->assertSee('Aceptar pedido');

        // el cliente cancela desde su enlace justo antes de que la empleada pulse «Aceptar»
        DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'cancelado', 'cerrado_en' => now()]);

        $this->post('/gestion/pedidos/E07C4A26/aceptar', ['hora' => '21:45'])
            ->assertRedirect('/gestion/pedidos/E07C4A26')
            ->assertSessionHas('error', 'No se ha aplicado: el cliente acaba de cancelar el pedido E07C4A26.');

        $pedido->refresh();
        $this->assertSame(['cancelado', null, null], [$pedido->estado, $pedido->hora_recogida, $pedido->usuario_id]);
    }

    /**
     * Un pedido que ya decidió otra persona no se vuelve a decidir.
     *
     * @return void
     */
    public function test_ya_decidido_por_otra_persona(): void
    {
        $this->pedidoPendiente();
        $this->post('/gestion/pedidos/E07C4A26/aceptar', ['hora' => '21:45']);

        $this->post('/gestion/pedidos/E07C4A26/rechazar', ['motivo' => 'Sin masa'])
            ->assertSessionHas('error', 'No se ha aplicado: el pedido E07C4A26 ya está «Aceptado».');

        $this->assertSame('aceptado', Pedido::where('codigo', 'E07C4A26')->value('estado'));
    }
}
