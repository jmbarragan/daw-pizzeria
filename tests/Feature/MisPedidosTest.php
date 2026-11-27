<?php

/**
 * Pruebas del historial del cliente registrado, del pedido con sesión y de repetir un pedido (RF-08, CU-09).
 */

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * El martes 24/11/2026 a las 20:40, Lucía tiene cuenta y un pedido, el
 * 1A2B3C26, con una Margherita y una Tartufo que ya se ha retirado. Antes de
 * registrarse pidió como invitada con el mismo correo (el 4D5E6F26), que no
 * es de su historial.
 */
class MisPedidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reloj, turno, carta, la cuenta de Lucía y sus dos pedidos.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        Notification::fake();

        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $pizzas = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        $margherita = Producto::create(['categoria_id' => $pizzas, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $tartufo = Producto::create(['categoria_id' => $pizzas, 'nombre' => 'Tartufo', 'precio' => '14.00']);
        $tartufo->forceFill(['retirado_en' => now()])->save();

        $lucia = $this->cliente(['contrasena' => $this->contrasenaDePrueba(), 'email_verificado_en' => now()]);
        $this->pedido($lucia, '1A2B3C26', [$margherita, $tartufo]);
        $this->pedido($this->cliente(), '4D5E6F26', [$margherita]);
    }

    /**
     * Guarda una Lucía Fernández con su correo: invitada, o con los datos de cuenta dados.
     *
     * @param  array<string, mixed>  $cuenta  contraseña y verificación, si tiene cuenta
     * @return Cliente
     */
    private function cliente(array $cuenta = []): Cliente
    {
        return Cliente::forceCreate($cuenta + [
            'nombre' => 'Lucía Fernández',
            'telefono' => '+34100123456',
            'email' => 'lucia@example.com',
        ]);
    }

    /**
     * Guarda un pedido recogido del cliente, con una unidad de cada producto.
     *
     * @param  Cliente  $cliente  cliente del pedido
     * @param  string  $codigo  código de 8 caracteres
     * @param  list<Producto>  $productos  productos de las líneas
     * @return void
     */
    private function pedido(Cliente $cliente, string $codigo, array $productos): void
    {
        $id = DB::table('pedidos')->insertGetId([
            'codigo' => $codigo,
            'cliente_id' => $cliente->id,
            'token_seguimiento' => hash('sha256', $codigo),
            'fecha_prevista' => '2026-11-20',
            'estado' => 'cancelado',
            'cerrado_en' => '2026-11-20 21:00:00',
        ]);
        foreach ($productos as $producto) {
            DB::table('lineas_pedido')->insert([
                'pedido_id' => $id,
                'producto_id' => $producto->id,
                'nombre_producto' => $producto->nombre,
                'precio_unitario' => $producto->precio,
                'cantidad' => 1,
            ]);
        }
    }

    /**
     * La cuenta de Lucía.
     *
     * @return Cliente
     */
    private function lucia(): Cliente
    {
        return Cliente::whereNotNull('contrasena')->sole();
    }

    /**
     * Sin sesión, «Mis pedidos» lleva al acceso de clientes, no al del personal.
     *
     * @return void
     */
    public function test_sin_sesion_lleva_al_acceso(): void
    {
        $this->get('/mis-pedidos')->assertRedirect('/acceder');
    }

    /**
     * El historial tiene el pedido de la cuenta con su total, y no el de invitada.
     *
     * @return void
     */
    public function test_historial_de_la_cuenta(): void
    {
        $this->actingAs($this->lucia(), 'cliente')->get('/mis-pedidos')
            ->assertOk()
            ->assertSee('1A2B3C26')
            ->assertSee('23,50 €')
            ->assertDontSee('4D5E6F26')
            ->assertSee('Mis pedidos');
    }

    /**
     * Repetir pone la Margherita en la cesta y avisa de que la Tartufo ya no se puede pedir.
     *
     * @return void
     */
    public function test_repetir_con_un_producto_retirado(): void
    {
        $this->actingAs($this->lucia(), 'cliente')->post('/mis-pedidos/1A2B3C26/repetir')
            ->assertRedirect('/cesta')
            ->assertSessionHas('estado', 'Ya no se puede pedir «Tartufo», así que se ha quedado fuera de la cesta.');

        $this->assertSame([Producto::where('nombre', 'Margherita')->value('id') => 1], session('cesta'));
    }

    /**
     * El pedido de otro cliente no se puede repetir.
     *
     * @return void
     */
    public function test_no_repite_un_pedido_ajeno(): void
    {
        $this->actingAs($this->lucia(), 'cliente')->post('/mis-pedidos/4D5E6F26/repetir')->assertNotFound();
    }

    /**
     * Con la sesión iniciada, el formulario sale relleno y el pedido es de la cuenta, sin crear un invitado.
     *
     * @return void
     */
    public function test_pedido_con_la_sesion_iniciada(): void
    {
        $lucia = $this->lucia();
        $this->actingAs($lucia, 'cliente')->post('/mis-pedidos/1A2B3C26/repetir');

        $this->get('/pedido')->assertSee('value="Lucía Fernández"', false)->assertSee('readonly', false);
        $this->post('/pedido', ['nombre' => 'Lucía F.', 'telefono' => '100 12 34 56', 'email' => 'otro@example.com', 'privacidad' => '1'])
            ->assertRedirect('/pedido/recibido');

        $pedido = Pedido::latest('id')->first();
        $this->assertSame([$lucia->id, 'lucia@example.com', 'Lucía F.'], [$pedido->cliente_id, $pedido->cliente->email, $pedido->cliente->nombre]);
        $this->assertSame(2, Cliente::count());
        $this->get('/pedido/recibido')->assertDontSee('Crear la cuenta');
    }

    /**
     * Si el personal anonimiza la cuenta, la sesión se cierra en la siguiente petición.
     *
     * @return void
     */
    public function test_cuenta_anonimizada_pierde_la_sesion(): void
    {
        // la sesión como la deja el acceso, para que cada petición lea al cliente de la base de datos
        $lucia = $this->lucia();
        $this->withSession([Auth::guard('cliente')->getName() => $lucia->id]);
        DB::table('clientes')->where('id', $lucia->id)
            ->update(['telefono' => null, 'email' => null, 'contrasena' => null, 'anonimizado_en' => now()]);

        $this->get('/mis-pedidos')->assertRedirect('/acceder');
        $this->assertGuest('cliente');
    }
}
