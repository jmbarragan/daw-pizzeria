<?php

/**
 * Pruebas de los correos de recepción, aceptación y rechazo (RF-05, RNF-02).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Notifications\AvisoDePedido;
use App\Notifications\PedidoAceptado;
use App\Notifications\PedidoRechazado;
use App\Notifications\PedidoRecibido;
use App\Servicios\CambiosDeEstado;
use App\Servicios\RegistroDePedidos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Las pruebas simulan el martes 24/11/2026 a las 20:40, con un turno de 20:00
 * a 23:30, la dirección del local en los ajustes y un pedido de 2 Margherita.
 */
class CorreosDePedidoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reloj, ajustes públicos y carta.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        config(['app.url' => 'https://pedidos.focaccia.example']);

        foreach (['telefono_local' => '+34100000000', 'direccion_local' => 'Calle Mayor, 12'] as $clave => $valor) {
            DB::table('ajustes')->insert([
                'clave' => $clave,
                'valor' => $valor,
                'publico' => true,
                'descripcion' => $clave,
            ]);
        }
        $pizzas = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        DB::table('productos')->insert(['categoria_id' => $pizzas, 'nombre' => 'Margherita', 'precio' => '9.50']);
    }

    /**
     * Registra un pedido de la web con 2 Margherita.
     *
     * @param  string|null  $correo  correo del cliente
     * @return Pedido
     */
    private function pedidoWeb(?string $correo = 'lucia@example.com'): Pedido
    {
        return app(RegistroDePedidos::class)->registrar(
            ['nombre' => 'Lucía Fernández', 'telefono' => '+34100123456', 'email' => $correo],
            collect([['producto' => Producto::sole(), 'unidades' => 2, 'importe' => 19.0]]),
            null,
            Carbon::parse('2026-11-24'),
        );
    }

    /**
     * Empleado que decide los pedidos.
     *
     * @return Usuario
     */
    private function empleado(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
    }

    /**
     * Correo enviado al cliente del pedido, ya preparado.
     *
     * @param  Pedido  $pedido  pedido del aviso
     * @param  class-string<AvisoDePedido>  $clase  tipo de aviso
     * @return MailMessage
     */
    private function correo(Pedido $pedido, string $clase): MailMessage
    {
        $avisos = Notification::sent($pedido->cliente, $clase);
        $this->assertCount(1, $avisos);

        return $avisos->first()->toMail($pedido->cliente);
    }

    /**
     * Al registrar un pedido de la web sale el de recepción, con las líneas, el total y el enlace.
     *
     * @return void
     */
    public function test_recepcion(): void
    {
        Notification::fake();
        $pedido = $this->pedidoWeb();

        $correo = $this->correo($pedido, PedidoRecibido::class);
        $this->assertSame("Hemos recibido tu pedido ({$pedido->codigo})", $correo->subject);
        $this->assertContains('2 × Margherita: 19,00 €', $correo->introLines);
        $this->assertContains('**Total: 19,00 €**, que se paga en el local al recogerlo.', $correo->introLines);
        $this->assertSame("https://pedidos.focaccia.example/seguimiento/{$pedido->token_seguimiento}", $correo->actionUrl);
    }

    /**
     * Al aceptarlo sale el de aceptación, con la hora y la dirección de recogida.
     *
     * @return void
     */
    public function test_aceptacion(): void
    {
        $pedido = $this->pedidoWeb();
        $turno = Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        Notification::fake();

        app(CambiosDeEstado::class)->aceptar($pedido, $this->empleado(), '21:45', $turno);

        $correo = $this->correo($pedido, PedidoAceptado::class);
        $this->assertContains('Recógelo hacia las **21:45** del 24/11 en Calle Mayor, 12.', $correo->introLines);
    }

    /**
     * Al rechazarlo sale el de rechazo, con el motivo y el teléfono del local.
     *
     * @return void
     */
    public function test_rechazo(): void
    {
        $pedido = $this->pedidoWeb();
        Notification::fake();

        app(CambiosDeEstado::class)->rechazar($pedido, $this->empleado(), 'Hoy no nos queda masa.');

        $correo = $this->correo($pedido, PedidoRechazado::class);
        $this->assertContains('Motivo: Hoy no nos queda masa.', $correo->introLines);
        $this->assertContains('Si tienes cualquier duda, llámanos al +34100000000.', $correo->introLines);
    }

    /**
     * El pedido telefónico recibe el de aceptación si da correo, y ninguno si no lo da.
     *
     * @return void
     */
    public function test_pedido_telefonico(): void
    {
        $turno = Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        Notification::fake();
        $registro = app(RegistroDePedidos::class);
        $lineas = collect([['producto' => Producto::sole(), 'unidades' => 2, 'importe' => 19.0]]);
        $dia = Carbon::parse('2026-11-24');

        $conCorreo = $registro->registrarTelefonico(['nombre' => 'Antonio Vega', 'telefono' => '+34100666777', 'email' => 'antonio@example.com'],
            $lineas, null, $dia, $this->empleado(), '22:00', $turno);
        $sinCorreo = $registro->registrarTelefonico(['nombre' => 'Rosa Gil', 'telefono' => '+34100666888', 'email' => null],
            $lineas, null, $dia, Usuario::sole(), '22:15', $turno);

        $this->assertContains('Recógelo hacia las **22:00** del 24/11 en Calle Mayor, 12.', $this->correo($conCorreo, PedidoAceptado::class)->introLines);
        Notification::assertNothingSentTo($sinCorreo->cliente);
    }

    /**
     * Cada correo va a la cola, después de confirmar la transacción, con 3 intentos espaciados.
     *
     * @return void
     */
    public function test_se_encola_con_tres_intentos(): void
    {
        $aviso = new PedidoRecibido(new Pedido);

        $this->assertSame([3, [60, 300], true], [$aviso->tries, $aviso->backoff, $aviso->afterCommit]);

        config(['queue.default' => 'database']);
        $this->pedidoWeb();
        $this->assertSame(1, DB::table('jobs')->count());
    }

    /**
     * Con el servidor de correo caído, el pedido queda guardado y el envío se reintenta más tarde.
     *
     * @return void
     */
    public function test_con_el_correo_caido_el_pedido_se_guarda(): void
    {
        // nadie escucha en el puerto 9: la conexión se rechaza al momento
        config(['queue.default' => 'database', 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 9]);
        $pedido = $this->pedidoWeb();

        $this->artisan('queue:work', ['--once' => true, '--stop-when-empty' => true]);

        $this->assertTrue($pedido->exists);
        $trabajo = DB::table('jobs')->sole();
        $this->assertSame(1, $trabajo->attempts);
        $this->assertSame(now()->addSeconds(60)->getTimestamp(), $trabajo->available_at);
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
