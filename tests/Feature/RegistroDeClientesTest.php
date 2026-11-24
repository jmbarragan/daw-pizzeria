<?php

/**
 * Pruebas del registro de clientes tras el pedido, la verificación del correo, el acceso y la recuperación (RF-08).
 */

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Notifications\RecuperarContrasenaDeCliente;
use App\Notifications\VerificarCorreoDeCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Las pruebas simulan el martes 24/11/2026 a las 20:40, con el local abierto
 * y una Margherita en la carta. Lucía pide como invitada y luego crea su cuenta.
 */
class RegistroDeClientesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Contraseña que elige Lucía en las pruebas.
     *
     * @var string
     */
    private string $clave;

    /**
     * Reloj, turno, carta y correos interceptados.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));
        Notification::fake();
        $this->clave = $this->contrasenaDePrueba();

        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $pizzas = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        Producto::create(['categoria_id' => $pizzas, 'nombre' => 'Margherita', 'precio' => '9.50']);
    }

    /**
     * Lucía pide una Margherita como invitada y se queda en la pantalla de pedido recibido.
     *
     * @return Pedido
     */
    private function pedirComoInvitada(): Pedido
    {
        $this->withSession(['cesta' => [Producto::sole()->id => 1]])->get('/pedido');
        $this->post('/pedido', [
            'nombre' => 'Lucía Fernández',
            'telefono' => '100 12 34 56',
            'email' => 'lucia@example.com',
            'privacidad' => '1',
        ])->assertRedirect('/pedido/recibido');

        return Pedido::latest('id')->first();
    }

    /**
     * Crea la cuenta desde la pantalla de pedido recibido.
     *
     * @return Cliente el cliente del pedido, ya con cuenta
     */
    private function registrarse(): Cliente
    {
        $pedido = $this->pedirComoInvitada();
        $this->post('/pedido/recibido/cuenta', ['contrasena' => $this->clave, 'contrasena_confirmation' => $this->clave])
            ->assertRedirect('/pedido/recibido')->assertSessionHas('estado');

        return $pedido->cliente->refresh();
    }

    /**
     * Intenta entrar con el correo de Lucía y la contraseña dada.
     *
     * @param  string  $clave  contraseña
     * @return TestResponse<Response>
     */
    private function entrar(string $clave)
    {
        return $this->post('/acceder', ['email' => 'lucia@example.com', 'contrasena' => $clave]);
    }

    /**
     * Tras pedir, se ofrece crear la cuenta; con la contraseña, el cliente del pedido pasa a tener cuenta.
     *
     * @return void
     */
    public function test_registro_tras_el_pedido(): void
    {
        $this->pedirComoInvitada();
        $this->get('/pedido/recibido')->assertSee('Crear la cuenta');

        $cliente = $this->registrarse();

        $this->assertFalse($cliente->esInvitado());
        $this->assertNull($cliente->email_verificado_en);
        Notification::assertSentTo($cliente, VerificarCorreoDeCliente::class);
    }

    /**
     * Sin verificar no se entra; con el enlace del correo, sí, y se llega a «Mis pedidos».
     *
     * @return void
     */
    public function test_verificacion_y_acceso(): void
    {
        $cliente = $this->registrarse();

        $this->entrar($this->clave)->assertSessionHasErrors('email');
        $this->assertGuest('cliente');

        $this->get(VerificarCorreoDeCliente::enlace($cliente))->assertRedirect('/acceder')->assertSessionHas('estado');
        $this->assertNotNull($cliente->refresh()->email_verificado_en);

        $this->entrar($this->clave)->assertRedirect('/mis-pedidos');
        $this->assertAuthenticatedAs($cliente, 'cliente');
    }

    /**
     * Un enlace con la firma alterada no verifica nada.
     *
     * @return void
     */
    public function test_enlace_de_verificacion_alterado(): void
    {
        $cliente = $this->registrarse();

        $this->get(VerificarCorreoDeCliente::enlace($cliente).'0')->assertForbidden();

        $this->assertNull($cliente->refresh()->email_verificado_en);
    }

    /**
     * Si el correo ya tiene cuenta, en el siguiente pedido se pide entrar en vez de registrarse.
     *
     * @return void
     */
    public function test_ya_hay_cuenta_con_ese_correo(): void
    {
        $this->registrarse();
        $this->flushSession();

        $this->pedirComoInvitada();
        $this->get('/pedido/recibido')->assertSee('Ya tienes una cuenta con este correo')->assertDontSee('Crear la cuenta');
        $this->post('/pedido/recibido/cuenta', ['contrasena' => $this->clave, 'contrasena_confirmation' => $this->clave])
            ->assertSessionHas('error');

        $this->assertSame(1, Cliente::whereNotNull('contrasena')->count());
    }

    /**
     * La recuperación va a la cuenta, no a los invitados con el mismo correo, y el enlace cambia la contraseña.
     *
     * @return void
     */
    public function test_recuperar_la_contrasena(): void
    {
        $cliente = $this->registrarse();
        $cliente->forceFill(['email_verificado_en' => now()])->save();
        $this->flushSession();
        $invitada = $this->pedirComoInvitada()->cliente;

        $this->post('/contrasena', ['email' => 'lucia@example.com'])->assertSessionHas('estado');

        $token = null;
        Notification::assertSentTo($cliente, RecuperarContrasenaDeCliente::class, function ($aviso) use (&$token) {
            $token = $aviso->token;

            return true;
        });
        Notification::assertNotSentTo($invitada, RecuperarContrasenaDeCliente::class);

        $nueva = $this->contrasenaDePrueba();
        $this->post('/contrasena/nueva', ['token' => $token, 'email' => 'lucia@example.com', 'contrasena' => $nueva, 'contrasena_confirmation' => $nueva])
            ->assertRedirect('/acceder');
        $this->entrar($nueva)->assertRedirect('/mis-pedidos');
    }
}
