<?php

/**
 * Prueba de integración de la compra de un invitado, de la carta al seguimiento (RF-01 a RF-05).
 */

namespace Tests\Feature;

use App\Models\Alergeno;
use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Recorre las pantallas públicas en el orden en que lo hace un cliente, sin
 * tocar la sesión ni la base de datos entre medias: lo único que se prepara es
 * la carta y el horario. Es la prueba que sirve de ejemplo en el informe de
 * pruebas para el envío completo.
 */
class CompraCompletaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un invitado pide dos pizzas y un agua y sigue su pedido con el enlace.
     *
     * @return void
     */
    public function test_de_la_carta_al_seguimiento(): void
    {
        $this->travelTo(Carbon::parse('2026-11-24 20:30'));
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);
        $margherita = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $margherita->alergenos()->attach(Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten']));
        $agua = Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua mineral', 'precio' => '1.50']);
        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);

        // 1. inicio y carta
        $this->get('/')->assertSee('Ahora abierto, hasta las 23:30.');
        $this->get('/carta')->assertSee('Margherita')->assertSee('Alérgenos: Gluten.');

        // 2. a la cesta: dos pizzas y un agua
        $this->post('/cesta', ['producto' => $margherita->id]);
        $this->post('/cesta', ['producto' => $margherita->id]);
        $this->post('/cesta', ['producto' => $agua->id]);
        $this->get('/cesta')->assertSee('Cesta (3)')->assertSee('20,50 €')->assertSee('Tramitar pedido');

        // 3. datos y confirmación
        $this->get('/pedido')->assertSee('Alérgenos: gluten.');
        $this->post('/pedido', [
            'nombre' => 'Lucía Fernández',
            'telefono' => '100 12 34 56',
            'email' => 'lucia.fernandez@example.com',
            'observaciones' => '',
            'privacidad' => '1',
        ])->assertRedirect('/pedido/recibido');

        // 4. pedido recibido con su enlace, y la cesta vacía
        $pedido = Pedido::sole();
        $recibido = $this->get('/pedido/recibido')->assertSee($pedido->codigo)->assertSee('Cesta (0)');
        preg_match('#href="([^"]+/seguimiento/[0-9a-f]{64})"#', $recibido->getContent(), $enlace);

        // 5. el enlace abre el seguimiento, que sigue funcionando sin la sesión
        $this->flushSession();
        $this->get($enlace[1])->assertOk()->assertSee($pedido->codigo)->assertSee('Pendiente');

        $this->assertSame(['pendiente', 2], [$pedido->estado, $pedido->lineas()->count()]);
        $this->assertSame('20.50', $pedido->total);
    }
}
