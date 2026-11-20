<?php

/**
 * Pruebas de la carta pública y de añadir a la cesta (RF-01, RF-03, P-02).
 */

namespace Tests\Feature;

use App\Models\Alergeno;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La carta es pública: no hace falta iniciar sesión. Cada prueba monta una
 * carta pequeña con pizzas y bebidas.
 */
class CartaPublicaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Categoría de las pizzas.
     *
     * @var Categoria
     */
    private Categoria $pizzas;

    /**
     * Pizza que se puede pedir, con gluten y lácteos.
     *
     * @var Producto
     */
    private Producto $margherita;

    /**
     * Pizza agotada.
     *
     * @var Producto
     */
    private Producto $tartufo;

    /**
     * Monta la carta de las pruebas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten y sus derivados']);
        $lacteos = Alergeno::create(['nombre' => 'Lácteos', 'descripcion' => 'Leche y sus derivados']);

        $this->margherita = Producto::create([
            'categoria_id' => $this->pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50',
            'descripcion' => 'Tomate, mozzarella fior di latte y albahaca',
        ]);
        $this->margherita->alergenos()->attach([$gluten->id, $lacteos->id]);

        $this->tartufo = Producto::create([
            'categoria_id' => $this->pizzas->id, 'nombre' => 'Tartufo', 'precio' => '14.50', 'disponible' => false,
        ]);
        Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua mineral', 'precio' => '1.50']);
    }

    /**
     * Las categorías salen en su orden, con precio en euros, descripción y alérgenos.
     *
     * @return void
     */
    public function test_la_carta_muestra_precio_descripcion_y_alergenos(): void
    {
        $this->get('/carta')
            ->assertOk()
            ->assertSee('<title>Carta · La Focaccia di Trevi</title>', false)
            ->assertSeeInOrder(['Pizzas', 'Margherita', '9,50 €', 'Tomate, mozzarella fior di latte y albahaca',
                'Alérgenos: Gluten, Lácteos.', 'Bebidas', 'Agua mineral', 'Sin alérgenos declarados.'])
            ->assertSeeInOrder(['Qué incluye cada alérgeno', 'Gluten', 'Cereales con gluten y sus derivados']);
    }

    /**
     * Un agotado se ve con su aviso, pero sin botón para añadirlo.
     *
     * @return void
     */
    public function test_un_agotado_se_ve_sin_boton(): void
    {
        $html = $this->get('/carta')->assertSee('Tartufo')->assertSee('Agotado')->getContent();

        $fichaTartufo = substr($html, strpos($html, 'id="producto-'.$this->tartufo->id.'"'));
        $fichaTartufo = substr($fichaTartufo, 0, strpos($fichaTartufo, '</li>'));
        $this->assertStringNotContainsString('name="producto"', $fichaTartufo);
    }

    /**
     * Las categorías ocultas y los productos retirados no salen; una categoría
     * vacía, tampoco.
     *
     * @return void
     */
    public function test_ocultos_y_retirados_no_salen(): void
    {
        $postres = Categoria::create(['nombre' => 'Postres', 'orden' => 3, 'activa' => false]);
        Producto::create(['categoria_id' => $postres->id, 'nombre' => 'Tiramisú', 'precio' => '5.00']);
        Producto::create(['categoria_id' => $this->pizzas->id, 'nombre' => 'Diavola', 'precio' => '11.00', 'retirado_en' => now()]);
        Categoria::create(['nombre' => 'Entrantes', 'orden' => 0, 'activa' => true]);

        $this->get('/carta')
            ->assertDontSee('Postres')->assertDontSee('Tiramisú')
            ->assertDontSee('Diavola')
            ->assertDontSee('Entrantes');
    }

    /**
     * Con el mismo orden, las categorías van por nombre.
     *
     * @return void
     */
    public function test_a_igual_orden_va_por_nombre(): void
    {
        $focaccias = Categoria::create(['nombre' => 'Focaccias', 'orden' => 1, 'activa' => true]);
        Producto::create(['categoria_id' => $focaccias->id, 'nombre' => 'Focaccia al rosmarino', 'precio' => '6.00']);

        $this->get('/carta')->assertSeeInOrder(['Focaccias', 'Pizzas', 'Bebidas']);
    }

    /**
     * Añadir suma una unidad y la cabecera cuenta las unidades de la cesta.
     *
     * @return void
     */
    public function test_anadir_a_la_cesta(): void
    {
        $this->post('/cesta', ['producto' => $this->margherita->id])
            ->assertRedirect('/carta#producto-'.$this->margherita->id)
            ->assertSessionHas('cesta', [$this->margherita->id => 1]);

        $this->post('/cesta', ['producto' => $this->margherita->id])
            ->assertSessionHas('estado', 'Se ha añadido «Margherita» a la cesta (2 en total).');

        $this->get('/carta')->assertSee('Cesta (2)');
    }

    /**
     * Forzar la petición no sirve para añadir un agotado, un retirado ni uno que no existe.
     *
     * @return void
     */
    public function test_no_se_puede_forzar_un_agotado_ni_un_retirado(): void
    {
        $diavola = Producto::create(['categoria_id' => $this->pizzas->id, 'nombre' => 'Diavola', 'precio' => '11.00', 'retirado_en' => now()]);

        $this->post('/cesta', ['producto' => $this->tartufo->id])
            ->assertSessionHas('error', 'Lo sentimos: «Tartufo» se ha agotado.');
        $this->post('/cesta', ['producto' => $diavola->id])
            ->assertSessionHas('error', 'Ese producto ya no está en la carta.');
        $this->post('/cesta', ['producto' => 999])
            ->assertSessionHas('error', 'Ese producto ya no está en la carta.');

        $this->assertSame([], session('cesta', []));
    }

    /**
     * De un mismo producto entran como mucho 20 unidades.
     *
     * @return void
     */
    public function test_como_mucho_veinte_del_mismo_producto(): void
    {
        $this->withSession(['cesta' => [$this->margherita->id => 20]])
            ->post('/cesta', ['producto' => $this->margherita->id])
            ->assertSessionHas('error', 'Ya tienes 20 unidades de «Margherita», que es lo máximo por producto.')
            ->assertSessionHas('cesta', [$this->margherita->id => 20]);
    }
}
