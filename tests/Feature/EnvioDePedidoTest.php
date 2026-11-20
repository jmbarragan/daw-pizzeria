<?php

/**
 * Pruebas del envío de un pedido como invitado (RF-04, CU-03, P-04 y P-05).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Servicios\CodigoDePedido;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pruebas pasan el martes 24/11/2026 a las 12:00, con el local cerrado y
 * un turno a las 13:00: el pedido se admite y su fecha prevista es ese día.
 */
class EnvioDePedidoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pizza de 9,50 €.
     *
     * @var Producto
     */
    private Producto $margherita;

    /**
     * Bebida de 1,50 €.
     *
     * @var Producto
     */
    private Producto $agua;

    /**
     * Datos del formulario que valen.
     *
     * @var array<string, string>
     */
    private const DATOS = [
        'nombre' => 'Lucía Fernández',
        'telefono' => '100 12 34 56',
        'email' => 'lucia.fernandez@example.com',
        'observaciones' => 'Sin albahaca, por favor',
        'privacidad' => '1',
    ];

    /**
     * Carta, turno y cesta de las pruebas, con el reloj en el martes a mediodía.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00'));

        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);
        $this->margherita = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $this->agua = Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua mineral', 'precio' => '1.50']);
        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '13:00', 'hora_cierre' => '16:00']);

        $this->withSession(['cesta' => [$this->margherita->id => 2, $this->agua->id => 1]]);
    }

    /**
     * Del formulario al pedido guardado: cliente, pedido pendiente con su
     * código y su token, líneas con el nombre y el precio del momento, y la
     * cesta vacía.
     *
     * @return void
     */
    public function test_envio_completo(): void
    {
        $this->get('/pedido')->assertOk()
            ->assertSee('Ahora cerrado. Abrimos hoy a las 13:00.')
            ->assertSeeInOrder(['1 × Agua mineral', '1,50 €', '2 × Margherita', '19,00 €', 'Total', '20,50 €']);

        // el teléfono de 9 cifras con espacios se guarda en E.164; 100… no es asignable
        $this->post('/pedido', ['telefono' => '+34 100 12 34 56'] + self::DATOS)->assertRedirect('/pedido/recibido');

        $pedido = Pedido::with('cliente')->sole();
        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}26$/', $pedido->codigo);
        $this->assertSame(['pendiente', 'web', '2026-11-24', 'Sin albahaca, por favor'],
            [$pedido->estado, $pedido->canal, $pedido->fecha_prevista->toDateString(), $pedido->observaciones]);
        $this->assertSame(hash_hmac('sha256', $pedido->codigo.':'.$pedido->creado_en->getTimestamp(), config('app.key')), $pedido->token_seguimiento);
        $this->assertSame(['Lucía Fernández', '+34100123456', 'lucia.fernandez@example.com', null],
            [$pedido->cliente->nombre, $pedido->cliente->telefono, $pedido->cliente->email, $pedido->cliente->contrasena]);

        $lineas = DB::table('lineas_pedido')->where('pedido_id', $pedido->id)->orderBy('nombre_producto')->get(['nombre_producto', 'precio_unitario', 'cantidad']);
        $this->assertSame([['Agua mineral', '1.50', 1], ['Margherita', '9.50', 2]],
            $lineas->map(fn ($l) => [$l->nombre_producto, $l->precio_unitario, $l->cantidad])->all());

        $this->assertNull(session('cesta'));
        $this->get('/pedido/recibido')->assertSee($pedido->codigo)->assertSee('pendiente');
    }

    /**
     * Si el precio cambia entre el formulario y la confirmación, se avisa y
     * se vuelve a confirmar; el pedido lleva el precio nuevo.
     *
     * @return void
     */
    public function test_precio_cambiado_entre_medias(): void
    {
        $this->get('/pedido');
        $this->margherita->update(['precio' => '10.00']);

        $this->post('/pedido', self::DATOS)
            ->assertRedirect('/pedido')
            ->assertSessionHas('error', 'Antes de enviarlo ha cambiado algo de tu pedido: «Margherita» ahora cuesta 10,00 €. Revísalo y confírmalo de nuevo.');
        $this->assertSame(0, Pedido::count());

        $this->get('/pedido')->assertSee('20,00 €');
        $this->post('/pedido', self::DATOS)->assertRedirect('/pedido/recibido');
        $this->assertSame('10.00', DB::table('lineas_pedido')->where('producto_id', $this->margherita->id)->value('precio_unitario'));
    }

    /**
     * Si un producto se agota entre medias, el pedido no se envía y se vuelve a la cesta.
     *
     * @return void
     */
    public function test_producto_agotado_entre_medias(): void
    {
        $this->get('/pedido');
        $this->margherita->update(['disponible' => false]);

        $this->post('/pedido', self::DATOS)
            ->assertRedirect('/cesta')
            ->assertSessionHas('error', 'Hay productos agotados en tu cesta: quítalos para poder enviar el pedido.');
        $this->assertSame(0, Pedido::count());
    }

    /**
     * Teléfono incompleto, correo mal escrito y política sin aceptar: errores junto a cada campo.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        $this->get('/pedido');

        $this->post('/pedido', ['telefono' => '1234', 'email' => 'lucia@', 'privacidad' => ''] + self::DATOS)
            ->assertSessionHasErrors([
                'telefono' => 'Indica un teléfono de 9 cifras, o con el prefijo de tu país (+…) si no es español.',
                'email',
                'privacidad' => 'Para enviar el pedido tienes que aceptar la política de privacidad.',
            ]);
        $this->assertSame(0, Pedido::count());
    }

    /**
     * Si el código sorteado ya existe, se sortea otro sin perder el pedido.
     *
     * @return void
     */
    public function test_codigo_repetido_se_vuelve_a_sortear(): void
    {
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Otro cliente', 'telefono' => '+34100000009']);
        DB::table('pedidos')->insert([
            'codigo' => 'AAAAAA26', 'cliente_id' => $cliente, 'token_seguimiento' => str_repeat('a', 64), 'fecha_prevista' => '2026-11-24',
        ]);

        /**
         * Sorteo simulado: la primera vez devuelve un código que ya está
         * guardado y la segunda uno libre, para ver que el registro reintenta.
         */
        $sorteo = new class extends CodigoDePedido
        {
            /**
             * Resultados de los sorteos, en orden: el primero repite el
             * código que ya existe y el segundo está libre.
             *
             * @var list<string>
             */
            private array $sorteos = ['AAAAAA26', 'BBBBBB26'];

            /**
             * Devuelve el siguiente resultado de la lista en lugar de sortear.
             *
             * @param  CarbonInterface  $fechaPrevista  no se usa: los códigos ya llevan el año
             * @return string
             */
            public function nuevo(CarbonInterface $fechaPrevista): string
            {
                return array_shift($this->sorteos);
            }
        };
        $this->app->instance(CodigoDePedido::class, $sorteo);

        $this->get('/pedido');
        $this->post('/pedido', self::DATOS)->assertRedirect('/pedido/recibido');

        $this->assertSame(['AAAAAA26', 'BBBBBB26'], Pedido::orderBy('codigo')->pluck('codigo')->all());
    }

    /**
     * Con la cesta vacía no hay formulario.
     *
     * @return void
     */
    public function test_sin_cesta_no_hay_formulario(): void
    {
        $this->withSession(['cesta' => []])->get('/pedido')
            ->assertRedirect('/cesta')
            ->assertSessionHas('error', 'Tu cesta está vacía.');
    }

    /**
     * La política de privacidad sale del ajuste público.
     *
     * @return void
     */
    public function test_politica_de_privacidad(): void
    {
        DB::table('ajustes')->insert(['clave' => 'politica_privacidad', 'valor' => "Responsable: La Focaccia di Trevi.\nPlazo: 30 días.", 'publico' => true, 'descripcion' => 'Política']);

        $this->get('/privacidad')->assertOk()->assertSee('Responsable: La Focaccia di Trevi.<br />', false);
    }
}
