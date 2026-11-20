<?php

/**
 * Prueba de integración del ciclo de un pedido, del envío a la recogida (RF-04 a RF-07 y RF-11 a RF-13).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Notifications\PedidoAceptado;
use App\Notifications\PedidoRechazado;
use App\Notifications\PedidoRecibido;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Cliente y personal se turnan en las mismas pantallas que usarían, sin
 * tocar la base de datos entre medias: solo se preparan la carta, el turno
 * y la cuenta del empleado. El martes 24/11/2026 a las 20:30 el local está
 * abierto de 20:00 a 23:30. Es la prueba de integración que se explica en el
 * informe de pruebas.
 *
 * Cada zona usa su usuario de MySQL, como en producción: el cliente,
 * focaccia_web, y el personal, focaccia_gestion. Como son dos conexiones, no
 * pueden compartir una transacción que se deshaga al final, así que los datos
 * se guardan de verdad y la prueba vacía las tablas al terminar.
 */
class CicloDelPedidoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ninguna transacción: las dos conexiones tienen que ver los mismos datos.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    /**
     * Tablas que llena la prueba, de las hijas a las madres.
     *
     * @var list<string>
     */
    private const TABLAS = ['lineas_pedido', 'pedidos', 'clientes', 'horarios', 'productos', 'categorias', 'usuarios'];

    /**
     * Reloj, carta con una pizza, turno de hoy y la empleada que atiende,
     * guardados con la conexión de administración.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:30'));
        DB::setDefaultConnection('admin');
        Notification::fake();

        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);

        $this->actingAs(Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * Vacía las tablas que ha llenado la prueba, para que las demás empiecen de cero.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $admin = DB::connection('admin');
        $admin->statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (self::TABLAS as $tabla) {
            $admin->table($tabla)->truncate();
        }
        $admin->statement('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
    }

    /**
     * La siguiente petición la hace el cliente, con el usuario de MySQL de la
     * zona pública. La gestión cambia de conexión sola, en su middleware.
     *
     * @return void
     */
    private function comoCliente(): void
    {
        DB::setDefaultConnection('web');
    }

    /**
     * Página de seguimiento que ve el cliente con su enlace.
     *
     * @param  string  $enlace  dirección del seguimiento
     * @return TestResponse<Response>
     */
    private function seguimiento(string $enlace): TestResponse
    {
        $this->comoCliente();

        return $this->get($enlace)->assertOk();
    }

    /**
     * El cliente pide dos Margherita por la web y se queda con el enlace del seguimiento.
     *
     * @return array{0: Pedido, 1: string} el pedido y la dirección del seguimiento
     */
    private function pedirPorLaWeb(): array
    {
        $margherita = Producto::sole()->id;
        $this->comoCliente();
        $this->post('/cesta', ['producto' => $margherita]);
        $this->post('/cesta', ['producto' => $margherita]);
        $this->get('/pedido')->assertSee('19,00 €');

        $this->post('/pedido', [
            'nombre' => 'Lucía Fernández',
            'telefono' => '100 12 34 56',
            'email' => 'lucia.fernandez@example.com',
            'observaciones' => 'Bien hecha, por favor',
            'privacidad' => '1',
        ])->assertRedirect('/pedido/recibido');

        $recibido = $this->get('/pedido/recibido')->getContent();
        preg_match('#href="[^"]*(/seguimiento/[0-9a-f]{64})"#', $recibido, $enlace);

        return [Pedido::sole(), $enlace[1]];
    }

    /**
     * Pendiente → aceptado → listo → recogido y pagado, con un correo al recibirlo y otro al aceptarlo.
     *
     * @return void
     */
    public function test_del_envio_a_la_recogida(): void
    {
        // 1. el cliente envía el pedido: queda pendiente y le llega el correo de recepción
        [$pedido, $enlace] = $this->pedirPorLaWeb();
        $this->seguimiento($enlace)->assertSee('Pendiente')->assertSee('Cancelar pedido');
        Notification::assertSentTo($pedido->cliente, PedidoRecibido::class);

        // 2. la empleada lo ve en el panel y lo acepta para las 21:00
        $this->get('/gestion/pedidos')->assertSee($pedido->codigo);
        $this->get("/gestion/pedidos/{$pedido->codigo}")->assertSee('Bien hecha, por favor')->assertSee('<option value="21:00"', false);
        $this->post("/gestion/pedidos/{$pedido->codigo}/aceptar", ['hora' => '21:00'])
            ->assertSessionHas('estado', "Pedido {$pedido->codigo} aceptado para recoger a las 21:00.");
        Notification::assertSentTo($pedido->cliente, PedidoAceptado::class);

        // 3. el cliente ya ve la hora y no puede cancelar
        $this->seguimiento($enlace)->assertSee('<strong>21:00</strong>', false)->assertDontSee('Cancelar pedido');

        // 4. listo, y al recogerlo se apunta el pago y se cierra
        $this->post("/gestion/pedidos/{$pedido->codigo}/estado", ['estado' => 'listo'])->assertSessionHas('estado');
        $this->seguimiento($enlace)->assertSee('Listo para recoger.');
        $this->post("/gestion/pedidos/{$pedido->codigo}/estado", ['estado' => 'recogido'])->assertSessionHas('estado');

        $pedido->refresh();
        $this->assertSame('recogido', $pedido->estado);
        $this->assertSame('2026-11-24 20:30', $pedido->pagado_en->format('Y-m-d H:i'));
        $this->assertNotNull($pedido->cerrado_en);
        $this->assertSame('19.00', $pedido->total);
        $this->seguimiento($enlace)->assertSee('Recogido. ¡Que aproveche!');
        Notification::assertCount(2);
    }

    /**
     * Si la empleada lo rechaza, el cliente recibe el motivo por correo y en el seguimiento.
     *
     * @return void
     */
    public function test_rechazo(): void
    {
        [$pedido, $enlace] = $this->pedirPorLaWeb();

        $this->post("/gestion/pedidos/{$pedido->codigo}/rechazar", ['motivo' => 'Nos hemos quedado sin masa.'])
            ->assertSessionHas('estado', "Pedido {$pedido->codigo} rechazado.");

        Notification::assertSentTo($pedido->cliente, PedidoRechazado::class);
        $this->seguimiento($enlace)->assertSee('Rechazado.')->assertSee('Nos hemos quedado sin masa.');
        $this->assertSame('rechazado', $pedido->refresh()->estado);
    }

    /**
     * Si el cliente cancela, la empleada ya no puede aceptarlo y se le avisa de por qué.
     *
     * @return void
     */
    public function test_cancelacion(): void
    {
        [$pedido, $enlace] = $this->pedirPorLaWeb();

        $this->comoCliente();
        $this->get("{$enlace}/cancelar")->assertSee('Sí, cancelar el pedido');
        $this->post("{$enlace}/cancelar")->assertSessionHas('estado', "Pedido {$pedido->codigo} cancelado.");

        $this->post("/gestion/pedidos/{$pedido->codigo}/aceptar", ['hora' => '21:00'])
            ->assertSessionHas('error', "No se ha aplicado: el cliente acaba de cancelar el pedido {$pedido->codigo}.");

        $this->assertSame('cancelado', $pedido->refresh()->estado);
        Notification::assertNotSentTo($pedido->cliente, PedidoAceptado::class);
    }
}
