<?php

/**
 * Pruebas de la cesta de la zona pública (RF-03, pantalla P-03).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cada prueba parte de una carta con pizzas y bebidas, un turno de hoy por la
 * noche y los ajustes públicos de teléfono y de máximo de pizzas.
 */
class CestaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pizza de 9,50 €.
     *
     * @var Producto
     */
    private Producto $margherita;

    /**
     * Otra pizza, de 11,00 €.
     *
     * @var Producto
     */
    private Producto $diavola;

    /**
     * Bebida de 1,50 €, que no cuenta como pizza.
     *
     * @var Producto
     */
    private Producto $agua;

    /**
     * Carta, turno y ajustes de las pruebas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);
        $this->margherita = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $this->diavola = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Diavola', 'precio' => '11.00']);
        $this->agua = Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua mineral', 'precio' => '1.50']);

        Horario::create(['dia' => today(), 'turno' => 1, 'hora_apertura' => '00:00', 'hora_cierre' => '23:59']);
        DB::table('ajustes')->insert([
            ['clave' => 'telefono_local', 'valor' => '+34100000000', 'publico' => true, 'descripcion' => 'Teléfono'],
            ['clave' => 'maximo_pizzas', 'valor' => '20', 'publico' => true, 'descripcion' => 'Máximo de pizzas'],
        ]);
    }

    /**
     * La cesta enseña cada línea con su importe y el total, con los precios de la carta.
     *
     * @return void
     */
    public function test_lineas_importes_y_total(): void
    {
        $this->withSession(['cesta' => [$this->margherita->id => 2, $this->agua->id => 1]])
            ->get('/cesta')
            ->assertOk()
            ->assertSeeInOrder(['Agua mineral', '1,50 €', 'Margherita', '19,00 €', 'Total', '20,50 €'])
            ->assertSee('href="'.url('/pedido').'"', false);
    }

    /**
     * Las unidades de una línea van de 1 a 20: 0 y 21 se rechazan con su motivo.
     *
     * @return void
     */
    public function test_limites_de_unidades_por_producto(): void
    {
        $this->withSession(['cesta' => [$this->agua->id => 3]]);

        $this->patch("/cesta/{$this->agua->id}", ['unidades' => 1])->assertSessionHas('cesta', [$this->agua->id => 1]);
        $this->patch("/cesta/{$this->agua->id}", ['unidades' => 20])->assertSessionHas('cesta', [$this->agua->id => 20]);

        $this->patch("/cesta/{$this->agua->id}", ['unidades' => 0])
            ->assertSessionHasErrors(['unidades' => 'Cada producto va de 1 a 20 unidades; para no pedirlo, usa «Quitar».']);
        $this->patch("/cesta/{$this->agua->id}", ['unidades' => 21])->assertSessionHasErrors('unidades');

        $this->assertSame([$this->agua->id => 20], session('cesta'));
    }

    /**
     * Quitar saca la línea entera.
     *
     * @return void
     */
    public function test_quitar_una_linea(): void
    {
        $this->withSession(['cesta' => [$this->margherita->id => 2, $this->agua->id => 1]])
            ->delete("/cesta/{$this->margherita->id}")
            ->assertRedirect('/cesta')
            ->assertSessionHas('cesta', [$this->agua->id => 1]);
    }

    /**
     * Veinte pizzas caben; la vigesimoprimera no, y la ventana pide llamar.
     * Las bebidas no cuentan.
     *
     * @return void
     */
    public function test_limite_de_pizzas(): void
    {
        $this->withSession(['cesta' => [$this->margherita->id => 12, $this->diavola->id => 8, $this->agua->id => 20]]);

        $this->post('/cesta', ['producto' => $this->margherita->id])
            ->assertSessionHas('error', 'Por la web se pueden pedir como mucho 20 pizzas.')
            ->assertSessionHas('aviso_pizzas', 20);
        $this->get('/carta')->assertSee('<dialog class="aviso-pizzas" data-abrir-al-cargar', false)->assertSee('tel:+34100000000', false);

        $this->patch("/cesta/{$this->diavola->id}", ['unidades' => 9])->assertSessionHas('aviso_pizzas', 20);
        $this->patch("/cesta/{$this->diavola->id}", ['unidades' => 7])->assertSessionDoesntHaveErrors();

        // de 19 a 20 pizzas justas se puede
        $this->patch("/cesta/{$this->diavola->id}", ['unidades' => 8])->assertSessionMissing('aviso_pizzas');

        $this->assertSame([$this->margherita->id => 12, $this->diavola->id => 8, $this->agua->id => 20], session('cesta'));
    }

    /**
     * Un producto agotado después de añadirlo se marca y no deja tramitar; uno
     * retirado desaparece de la cesta con un aviso.
     *
     * @return void
     */
    public function test_agotados_y_retirados_en_la_cesta(): void
    {
        $this->diavola->update(['disponible' => false]);
        $this->agua->update(['retirado_en' => now()]);

        $this->withSession(['cesta' => [$this->margherita->id => 1, $this->diavola->id => 1, $this->agua->id => 1]])
            ->get('/cesta')
            ->assertSee('Hemos quitado de tu cesta un producto que ya no está en la carta.')
            ->assertSee('Un producto de tu cesta se ha agotado')
            ->assertDontSee('Agua mineral')
            ->assertDontSee('href="'.url('/pedido').'"', false);

        $this->assertSame([$this->margherita->id => 1, $this->diavola->id => 1], session('cesta'));
    }

    /**
     * Sin ninguna apertura prevista no se puede tramitar y se pide llamar.
     *
     * @return void
     */
    public function test_sin_aperturas_no_se_tramita(): void
    {
        Horario::query()->delete();

        $this->withSession(['cesta' => [$this->margherita->id => 1]])
            ->get('/cesta')
            ->assertSee('Ahora no tenemos fechas de apertura previstas')
            ->assertDontSee('href="'.url('/pedido').'"', false);
    }

    /**
     * Cambiar o quitar un producto que no está en la cesta no dice su nombre:
     * así no se pueden sacar los nombres de productos ocultos probando números.
     *
     * @return void
     */
    public function test_no_revela_productos_que_no_estan_en_la_cesta(): void
    {
        $oculto = Producto::create(['categoria_id' => $this->margherita->categoria_id, 'nombre' => 'Pizza secreta', 'precio' => '30.00', 'retirado_en' => now()]);
        $this->withSession(['cesta' => [$this->agua->id => 1]]);

        $this->delete("/cesta/{$oculto->id}")
            ->assertSessionHas('error', 'Ese producto no está en tu cesta.')
            ->assertSessionMissing('estado');
        $this->patch("/cesta/{$oculto->id}", ['unidades' => 2])
            ->assertSessionHas('error', 'Ese producto no está en tu cesta.');

        $this->get('/cesta')->assertDontSee('Pizza secreta');
        $this->assertSame([$this->agua->id => 1], session('cesta'));
    }

    /**
     * Una cesta vacía invita a ver la carta.
     *
     * @return void
     */
    public function test_cesta_vacia(): void
    {
        $this->get('/cesta')->assertOk()->assertSee('Todavía no has añadido nada.');
    }
}
