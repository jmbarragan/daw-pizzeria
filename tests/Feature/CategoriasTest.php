<?php

/**
 * Pruebas de la gestión de categorías de la carta (RF-15, CU-14).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pantallas de la carta usan la conexión «gestion», así que la prueba
 * abre la transacción en esa conexión y crea allí sus datos, con una
 * empleada ya identificada: la carta no es solo del administrador.
 */
class CategoriasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Identifica a una empleada en la conexión de gestión.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');

        $this->actingAs(Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => 'Trevi.2026.horno',
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * El listado sigue el orden de la carta, no el alfabético, y dice cuáles están ocultas.
     *
     * @return void
     */
    public function test_el_listado_sigue_el_orden_de_la_carta(): void
    {
        Categoria::create(['nombre' => 'Postres', 'orden' => 4, 'activa' => true]);
        Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        Categoria::create(['nombre' => 'Bebidas', 'orden' => 5, 'activa' => false]);

        $this->get('/gestion/carta/categorias')
            ->assertOk()
            ->assertSeeInOrder(['Pizzas', 'Postres', 'Bebidas'])
            ->assertSee('Oculta')
            ->assertSee('aria-current="page"', false);
    }

    /**
     * El alta propone ir al final de la carta y guarda la categoría.
     *
     * @return void
     */
    public function test_alta_de_una_categoria(): void
    {
        Categoria::create(['nombre' => 'Pizzas', 'orden' => 3, 'activa' => true]);

        $this->get('/gestion/carta/categorias/nueva')->assertOk()->assertSee('value="4"', false);

        $this->post('/gestion/carta/categorias', ['nombre' => '  Focaccias ', 'orden' => 2, 'activa' => '1'])
            ->assertRedirect('/gestion/carta/categorias')
            ->assertSessionHas('estado', 'Categoría «Focaccias» creada.');

        $this->assertDatabaseHas('categorias', ['nombre' => 'Focaccias', 'orden' => 2, 'activa' => 1], 'gestion');
    }

    /**
     * Nombre repetido, demasiado corto u orden fuera de la columna: errores junto a cada campo.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);

        $this->from('/gestion/carta/categorias/nueva')
            ->post('/gestion/carta/categorias', ['nombre' => 'Pizzas', 'orden' => 300])
            ->assertRedirect('/gestion/carta/categorias/nueva')
            ->assertSessionHasErrors([
                'nombre' => 'Ya hay una categoría con ese nombre.',
                'orden' => 'El orden va de 0 a 255; las categorías con número más bajo salen antes.',
            ]);

        $this->post('/gestion/carta/categorias', ['nombre' => 'P', 'orden' => 'primero'])
            ->assertSessionHasErrors(['nombre', 'orden']);

        $this->assertSame(1, Categoria::count());
    }

    /**
     * Al editar se puede cambiar el orden y ocultarla; el nombre puede seguir siendo el suyo.
     *
     * @return void
     */
    public function test_editar_y_ocultar(): void
    {
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 5, 'activa' => true]);

        $this->get("/gestion/carta/categorias/{$bebidas->id}/editar")->assertOk()->assertSee('value="Bebidas"', false);

        $this->put("/gestion/carta/categorias/{$bebidas->id}", ['nombre' => 'Bebidas', 'orden' => 9, 'activa' => '0'])
            ->assertRedirect('/gestion/carta/categorias')
            ->assertSessionHas('estado', 'Categoría «Bebidas» guardada.');

        $bebidas->refresh();
        $this->assertSame([9, false], [$bebidas->orden, $bebidas->activa]);
    }

    /**
     * Una categoría vacía se borra.
     *
     * @return void
     */
    public function test_borrar_una_categoria_vacia(): void
    {
        $entrantes = Categoria::create(['nombre' => 'Entrantes', 'orden' => 3, 'activa' => true]);

        $this->delete("/gestion/carta/categorias/{$entrantes->id}")
            ->assertRedirect('/gestion/carta/categorias')
            ->assertSessionHas('estado', 'Categoría «Entrantes» borrada.');

        $this->assertModelMissing($entrantes);
    }

    /**
     * Con productos, aunque estén retirados, no se borra: se explica y se propone ocultarla.
     *
     * @return void
     */
    public function test_no_se_borra_una_categoria_con_productos(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Diavola', 'precio' => '11.00', 'retirado_en' => now()]);

        $this->delete("/gestion/carta/categorias/{$pizzas->id}")
            ->assertRedirect('/gestion/carta/categorias')
            ->assertSessionHas('error', 'No se puede borrar «Pizzas» porque tiene 2 productos, también los retirados. '
                .'Si no quieres que se vea en la carta, edítala y desmarca «Visible en la carta».');

        $this->assertModelExists($pizzas);
    }

    /**
     * Sin sesión, la carta de la gestión manda al acceso.
     *
     * @return void
     */
    public function test_sin_sesion_no_se_entra(): void
    {
        $this->post('/gestion/salir');

        $this->get('/gestion/carta/categorias')->assertRedirect('/gestion/acceso');
    }
}
