<?php

/**
 * Pruebas del correo repetido entre invitados y de los límites de los correos.
 */

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Notifications\PedidoRecibido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Las pruebas simulan el martes 24/11/2026 a las 20:40, con el local abierto y
 * una Margherita en la carta, y piden por la web como un invitado.
 */
class InvitadosYLimitesDeCorreoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reloj, turno de hoy y carta.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        Notification::fake();

        DB::table('horarios')->insert(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $pizzas = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        DB::table('productos')->insert(['categoria_id' => $pizzas, 'nombre' => 'Margherita', 'precio' => '9.50']);
    }

    /**
     * Pone una Margherita en la cesta, abre el formulario y envía el pedido.
     *
     * @param  string  $correo  correo del invitado
     * @param  string  $nombre  nombre del invitado
     * @return TestResponse<Response>
     */
    private function pedir(string $correo, string $nombre = 'Lucía Fernández')
    {
        // el formulario apunta en la sesión los precios que ha visto el cliente
        $this->withSession(['cesta' => [DB::table('productos')->value('id') => 1]])->get('/pedido')->assertOk();

        return $this->post('/pedido', [
            'nombre' => $nombre,
            'telefono' => '100 123 456',
            'email' => $correo,
            'observaciones' => '',
            'privacidad' => '1',
        ]);
    }

    /**
     * Un invitado que vuelve a pedir con el mismo correo tiene su segundo pedido, con su propio cliente.
     *
     * @return void
     */
    public function test_un_invitado_repite_correo(): void
    {
        $this->pedir('lucia@example.com')->assertRedirect('/pedido/recibido');
        $this->pedir('lucia@example.com')->assertRedirect('/pedido/recibido');

        $this->assertSame(2, Pedido::count());
        $this->assertSame(2, Cliente::where('email', 'lucia@example.com')->count());
    }

    /**
     * Entre clientes con cuenta el correo sigue siendo único; un invitado sí puede usar el de una cuenta.
     *
     * @return void
     *
     * @throws UniqueConstraintViolationException en la segunda cuenta con el mismo correo, que es lo que se espera
     */
    public function test_dos_cuentas_no_comparten_correo(): void
    {
        $cuenta = ['nombre' => 'Ana Ruiz', 'telefono' => '+34100200300', 'email' => 'ana@example.com', 'contrasena' => 'x'];
        DB::table('clientes')->insert($cuenta);
        DB::table('clientes')->insert(['contrasena' => null] + $cuenta);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('clientes')->insert($cuenta);
    }

    /**
     * Desde la misma IP se admiten 5 pedidos por minuto; el sexto vuelve con un aviso y no manda correo.
     *
     * @return void
     */
    public function test_limite_por_ip(): void
    {
        foreach (range(1, 5) as $n) {
            $this->pedir("invitado{$n}@example.com")->assertRedirect('/pedido/recibido');
        }

        $this->pedir('invitado6@example.com')->assertSessionHas('error');

        $this->assertSame(5, Pedido::count());
        Notification::assertCount(5);
    }

    /**
     * Para el mismo correo se admiten 10 pedidos por hora, aunque lleguen desde varias IP.
     *
     * @return void
     */
    public function test_limite_por_correo(): void
    {
        foreach (range(1, 11) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => "198.51.100.{$n}"]);
            $respuesta = $this->pedir('Lucia@Example.com ');
        }

        $respuesta->assertSessionHas('error');
        $this->assertSame(10, Pedido::count());
    }

    /**
     * Los envíos que no pasan la validación no gastan el cupo del correo: nadie
     * puede dejar sin pedir a otra persona escribiendo su correo.
     *
     * @return void
     */
    public function test_los_envios_fallidos_no_gastan_el_cupo(): void
    {
        foreach (range(1, 12) as $n) {
            $this->withServerVariables(['REMOTE_ADDR' => "203.0.113.{$n}"]);
            $this->post('/pedido', ['email' => 'lucia@example.com', 'privacidad' => ''])->assertSessionHasErrors();
        }

        $this->pedir('lucia@example.com')->assertRedirect('/pedido/recibido');
    }

    /**
     * Un nombre con un enlace en Markdown sale escrito tal cual, no como enlace.
     *
     * @return void
     */
    public function test_el_nombre_no_se_convierte_en_enlace(): void
    {
        $this->pedir('lucia@example.com', '[Pulsa aquí](https://malo.example)');

        $cliente = Cliente::sole();
        $html = (string) Notification::sent($cliente, PedidoRecibido::class)->first()->toMail($cliente)->render();

        $this->assertStringNotContainsString('href="https://malo.example"', $html);
        $this->assertStringContainsString('[Pulsa aquí](https://malo.example)', $html);
    }
}
