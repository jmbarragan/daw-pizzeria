<?php

/**
 * Prueba de integración del cliente que se registra, de su primer pedido al segundo (RF-08, CU-07 a CU-09).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Notifications\PedidoRecibido;
use App\Notifications\VerificarCorreoDeCliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Recorre las pantallas públicas en el orden en que lo hace una clienta que
 * pide como invitada, crea su cuenta, la confirma, entra, repite el pedido y
 * lo envía con la sesión iniciada. Solo se preparan la carta y el horario.
 * Es la segunda prueba de integración del informe de pruebas, con la
 * compra completa del invitado y el ciclo del pedido.
 */
class RecorridoDelClienteRegistradoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lucía pide dos Margherita, se registra, entra y repite el pedido.
     *
     * @return void
     */
    public function test_del_primer_pedido_al_segundo_con_la_cuenta(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 20:30'));
        Notification::fake();
        $clave = $this->contrasenaDePrueba();
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $margherita = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $datos = ['nombre' => 'Lucía Fernández', 'telefono' => '100 12 34 56', 'email' => 'lucia@example.com', 'privacidad' => '1'];

        // 1. pide como invitada y, en la pantalla de recibido, crea la cuenta con una contraseña
        $this->post('/cesta', ['producto' => $margherita->id]);
        $this->post('/cesta', ['producto' => $margherita->id]);
        $this->get('/pedido');
        $this->post('/pedido', $datos)->assertRedirect('/pedido/recibido');
        $this->get('/pedido/recibido')->assertSee('Crear la cuenta');
        $this->post('/pedido/recibido/cuenta', ['contrasena' => $clave, 'contrasena_confirmation' => $clave])
            ->assertSessionHas('estado');
        $lucia = Cliente::whereNotNull('contrasena')->sole();

        // 2. sin confirmar el correo no entra; con el enlace del correo, sí
        $this->post('/acceder', ['email' => 'lucia@example.com', 'contrasena' => $clave])->assertSessionHasErrors('email');
        Notification::assertSentTo($lucia, VerificarCorreoDeCliente::class);
        $this->get(VerificarCorreoDeCliente::enlace($lucia))->assertRedirect('/acceder');
        $this->post('/acceder', ['email' => 'lucia@example.com', 'contrasena' => $clave])->assertRedirect('/mis-pedidos');

        // 3. en «Mis pedidos» está el primero, y «Repetir» lo pone en la cesta
        $primero = Pedido::sole();
        $this->get('/mis-pedidos')->assertSee($primero->codigo)->assertSee('19,00 €');
        $this->post("/mis-pedidos/{$primero->codigo}/repetir")->assertRedirect('/cesta');
        $this->get('/cesta')->assertSee('Cesta (2)');

        // 4. con la sesión iniciada el formulario sale relleno y el pedido es de su cuenta
        $this->get('/pedido')->assertSee('value="Lucía Fernández"', false);
        $this->post('/pedido', $datos)->assertRedirect('/pedido/recibido');
        $this->get('/pedido/recibido')->assertDontSee('Crear la cuenta');

        // 5. los dos pedidos son de la cuenta y salen en «Mis pedidos»; cada uno tuvo su correo de recepción
        $this->assertSame([$lucia->id, $lucia->id], Pedido::orderBy('id')->pluck('cliente_id')->all());
        $segundo = Pedido::latest('id')->first();
        $this->get('/mis-pedidos')->assertSeeInOrder([$segundo->codigo, $primero->codigo]);
        Notification::assertSentToTimes($lucia, PedidoRecibido::class, 2);
        $this->assertSame(1, Cliente::count());
    }
}
