<?php

/**
 * Pruebas del enlace de seguimiento de un pedido (RF-05, P-05).
 */

namespace Tests\Feature;

use App\Models\Pedido;
use App\Servicios\EnlaceDeSeguimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * El token se fabrica con la clave de la aplicación: cambiar cualquier dato
 * lo cambia entero, y uno alterado no abre nada ni deja ver datos.
 */
class EnlaceDeSeguimientoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guarda el pedido 7B3E0A26, pendiente, con el token que le corresponde.
     *
     * @return Pedido
     */
    private function pedidoPendiente(): Pedido
    {
        $creado = Carbon::parse('2026-11-24 12:00:00');
        $cliente = DB::table('clientes')->insertGetId([
            'nombre' => 'Lucía Fernández',
            'telefono' => '+34100123456',
            'email' => 'lucia@example.com',
        ]);

        return Pedido::forceCreate([
            'codigo' => '7B3E0A26',
            'cliente_id' => $cliente,
            'creado_en' => $creado,
            'fecha_prevista' => '2026-11-24',
            'token_seguimiento' => (new EnlaceDeSeguimiento)->calcular('7B3E0A26', $creado),
        ]);
    }

    /**
     * Es el HMAC-SHA256 del código y la marca UNIX con la clave de la aplicación,
     * y cambia por completo si cambia el código, la hora o la clave.
     *
     * @return void
     */
    public function test_el_token_depende_del_codigo_la_hora_y_la_clave(): void
    {
        $enlaces = new EnlaceDeSeguimiento;
        $hora = Carbon::parse('2026-11-24 12:00:00');
        $token = $enlaces->calcular('7B3E0A26', $hora);

        $this->assertSame(hash_hmac('sha256', '7B3E0A26:'.$hora->getTimestamp(), config('app.key')), $token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);

        // otro código u otro segundo dan un token distinto
        $this->assertNotSame($token, $enlaces->calcular('7B3E0A27', $hora));
        $this->assertNotSame($token, $enlaces->calcular('7B3E0A26', $hora->copy()->addSecond()));

        // y con otra clave, también
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertNotSame($token, $enlaces->calcular('7B3E0A26', $hora));
    }

    /**
     * El enlace bueno muestra el código y el estado del pedido, sin dejar que lo indexen.
     *
     * @return void
     */
    public function test_el_enlace_bueno_muestra_el_pedido(): void
    {
        $pedido = $this->pedidoPendiente();

        $this->get((new EnlaceDeSeguimiento)->direccion($pedido))
            ->assertOk()
            ->assertSee('7B3E0A26')
            ->assertSee('Pendiente: lo revisaremos')
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    /**
     * Un token con un carácter cambiado, inventado o mal formado da la página 404 sin datos.
     *
     * @return void
     */
    public function test_un_token_alterado_no_da_datos(): void
    {
        $bueno = $this->pedidoPendiente()->token_seguimiento;
        $ultimo = $bueno[63] === 'a' ? 'b' : 'a';

        $this->get('/seguimiento/'.substr($bueno, 0, 63).$ultimo)->assertNotFound()->assertDontSee('7B3E0A26');
        $this->get('/seguimiento/'.str_repeat('0', 64))->assertNotFound();
        $this->get('/seguimiento/no-es-un-token')->assertNotFound();
        $this->get('/seguimiento/'.strtoupper($bueno))->assertNotFound()->assertDontSee('7B3E0A26');
    }

    /**
     * Un token guardado en la base que no sale de la clave no abre el pedido.
     *
     * @return void
     */
    public function test_un_token_que_no_firma_la_clave_no_vale(): void
    {
        $pedido = $this->pedidoPendiente();
        $falso = hash('sha256', 'cualquier cosa');
        DB::table('pedidos')->where('id', $pedido->id)->update(['token_seguimiento' => $falso]);

        $this->get("/seguimiento/{$falso}")->assertNotFound()->assertDontSee('7B3E0A26');
    }

    /**
     * La pantalla de pedido recibido lleva el enlace, como botón y escrito.
     *
     * @return void
     */
    public function test_la_pantalla_de_recibido_lleva_el_enlace(): void
    {
        $pedido = $this->pedidoPendiente();
        $enlace = (new EnlaceDeSeguimiento)->direccion($pedido);

        $this->withSession(['pedido_recibido' => $pedido->id])
            ->get('/pedido/recibido')
            ->assertSee('Ver el seguimiento')
            ->assertSee('href="'.$enlace.'"', false)
            ->assertSee($enlace);
    }
}
