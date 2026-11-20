<?php

/**
 * Pruebas del alta de pedidos telefónicos desde la gestión (RF-14).
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
 * Las pruebas pasan el martes 24/11/2026 a las 20:40, con un turno hoy de
 * 20:00 a 23:30 y otro el jueves, la conexión «gestion» y un empleado.
 */
class PedidoTelefonicoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Identificadores de los productos de la carta de las pruebas, por nombre.
     *
     * @var array<string, int>
     */
    private array $productos = [];

    /**
     * Reloj, conexión, empleado, turnos y una carta con una pizza agotada.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        DB::setDefaultConnection('gestion');

        $this->actingAs(Usuario::create([
            'nombre' => 'Pablo Gil',
            'email' => 'pablo.gil@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');

        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        Horario::create(['dia' => '2026-11-26', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);

        $pizzas = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        foreach ([['Margherita', '9.50', 1], ['Diavola', '11.00', 1], ['Tartufo', '14.50', 0]] as [$nombre, $precio, $disponible]) {
            $this->productos[$nombre] = DB::table('productos')->insertGetId([
                'categoria_id' => $pizzas, 'nombre' => $nombre, 'precio' => $precio, 'disponible' => $disponible,
            ]);
        }
    }

    /**
     * Datos de un pedido telefónico que valen.
     *
     * @param  array<string, mixed>  $cambios  campos que cambian
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Antonio Vega',
            'telefono' => '100 666 777',
            'email' => '',
            'observaciones' => 'Pasa a las 22:00',
            'unidades' => [$this->productos['Margherita'] => 2, $this->productos['Diavola'] => 0],
            'hora' => '22:00',
        ];
    }

    /**
     * El formulario ofrece los productos que se pueden pedir y las franjas que quedan hoy.
     *
     * @return void
     */
    public function test_el_formulario(): void
    {
        $this->get('/gestion/pedidos/telefonico')
            ->assertOk()
            ->assertSee('Margherita')
            ->assertDontSee('Tartufo')
            ->assertSee('<option value="20:45"', false)
            ->assertDontSee('<option value="20:30"', false);
    }

    /**
     * Sin correo: nace aceptado, por teléfono, con su hora, quién lo apunta y las líneas del momento.
     *
     * @return void
     */
    public function test_alta_sin_correo(): void
    {
        $this->post('/gestion/pedidos/telefonico', $this->datos())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('estado');

        $pedido = Pedido::with(['cliente', 'usuario', 'lineas'])->sole();
        $this->assertSame(['aceptado', 'telefono', '2026-11-24 22:00', 'Pablo Gil'], [
            $pedido->estado, $pedido->canal, $pedido->hora_recogida->format('Y-m-d H:i'), $pedido->usuario->nombre,
        ]);
        $this->assertSame(['Antonio Vega', '+34100666777', null], [$pedido->cliente->nombre, $pedido->cliente->telefono, $pedido->cliente->email]);
        $this->assertSame([['Margherita', 2, '9.50']], $pedido->lineas->map(fn ($l) => [$l->nombre_producto, $l->cantidad, $l->precio_unitario])->all());
        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}26$/', $pedido->codigo);

        $this->get('/gestion/pedidos?vista=hoy')->assertSee($pedido->codigo)->assertSee('Teléfono');
    }

    /**
     * Con correo, se guarda para mandarle la confirmación.
     *
     * @return void
     */
    public function test_alta_con_correo(): void
    {
        $this->post('/gestion/pedidos/telefonico', $this->datos(['email' => 'antonio.vega@example.com']))->assertSessionHasNoErrors();

        $this->assertSame('antonio.vega@example.com', Pedido::sole()->cliente->email);
    }

    /**
     * Por teléfono no hay máximo de pizzas: 25 se apuntan sin problema.
     *
     * @return void
     */
    public function test_sin_maximo_de_pizzas(): void
    {
        $unidades = [$this->productos['Margherita'] => 20, $this->productos['Diavola'] => 5];

        $this->post('/gestion/pedidos/telefonico', $this->datos(['unidades' => $unidades]))->assertSessionHasNoErrors();

        $this->assertSame(25, (int) Pedido::sole()->lineas()->sum('cantidad'));
    }

    /**
     * Sin productos, con 21 de uno, con un agotado o con una hora que no está: no se apunta.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        $this->post('/gestion/pedidos/telefonico', $this->datos(['unidades' => [$this->productos['Margherita'] => 0]]))
            ->assertSessionHasErrors(['unidades' => 'Apunta al menos un producto.']);

        $this->post('/gestion/pedidos/telefonico', $this->datos(['unidades' => [$this->productos['Margherita'] => 21]]))
            ->assertSessionHasErrors(['unidades.'.$this->productos['Margherita'] => 'Cada producto va de 1 a 20 unidades.']);

        $this->post('/gestion/pedidos/telefonico', $this->datos(['unidades' => [$this->productos['Tartufo'] => 1]]))
            ->assertSessionHasErrors(['unidades' => 'Algún producto ya no se puede pedir: está agotado o fuera de la carta.']);

        $this->post('/gestion/pedidos/telefonico', $this->datos(['hora' => '20:30']))
            ->assertSessionHasErrors(['hora' => 'Esa hora ya no está disponible: elige otra de la lista.']);

        $this->assertSame(0, Pedido::count());
    }

    /**
     * Cuando hoy ya no quedan franjas, el pedido va al siguiente día con turno.
     *
     * @return void
     */
    public function test_sin_franjas_hoy_va_al_siguiente_dia(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 23:20'));

        $this->get('/gestion/pedidos/telefonico')->assertSee('Recogida el jueves 26/11');
        $this->post('/gestion/pedidos/telefonico', $this->datos(['hora' => '21:00']))->assertSessionHasNoErrors();

        $this->assertSame('2026-11-26 21:00', Pedido::sole()->hora_recogida->format('Y-m-d H:i'));
    }
}
