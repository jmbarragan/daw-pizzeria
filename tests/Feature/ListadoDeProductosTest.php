<?php

/**
 * Pruebas del listado de productos de la gestión (RF-16, pantalla G-04).
 */

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lo que se ve en /gestion/carta: el orden, el precio en euros, el estado de
 * cada producto, la búsqueda, el filtro y las páginas de 15 filas. El alta,
 * la edición y el borrado están en ProductosTest.
 */
class ListadoDeProductosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Entra una empleada con la conexión de gestión.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');

        $this->actingAs(Usuario::create([
            'nombre' => 'Sergio Molina',
            'email' => 'sergio.molina@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * Va por categoría y nombre, con el precio en euros y los tres estados; a
     * los retirados se les ofrece volver a la carta.
     *
     * @return void
     */
    public function test_orden_precio_y_estado(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);

        Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua', 'precio' => '1.50']);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Tartufo', 'precio' => '14.50', 'disponible' => false]);
        Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Diavola', 'precio' => '11.00', 'retirado_en' => now()]);

        $this->get('/gestion/carta')
            ->assertOk()
            ->assertSeeInOrder(['Diavola', 'Retirado', 'Margherita', '9,50 €', 'Disponible', 'Tartufo', 'Agotado', 'Agua', '1,50 €'])
            ->assertSee('Volver a la carta');
    }

    /**
     * Buscar «agu» encuentra el agua; un «%» se busca como letra y no como comodín.
     *
     * @return void
     */
    public function test_busqueda_por_nombre(): void
    {
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 1, 'activa' => true]);
        Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua', 'precio' => '1.50']);
        Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Limonada', 'precio' => '2.50']);

        $this->get('/gestion/carta?buscar=agu')->assertSee('Agua')->assertDontSee('Limonada');
        $this->get('/gestion/carta?buscar=%25')->assertSee('Ningún producto coincide con la búsqueda.');
    }

    /**
     * Con el filtro de una categoría salen 15 por página, y el enlace a la
     * siguiente conserva el filtro.
     *
     * @return void
     */
    public function test_filtro_y_paginas_de_quince(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $bebidas = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activa' => true]);
        Producto::create(['categoria_id' => $bebidas->id, 'nombre' => 'Agua', 'precio' => '1.50']);

        foreach (range(1, 17) as $numero) {
            Producto::create(['categoria_id' => $pizzas->id, 'nombre' => sprintf('Pizza %02d', $numero), 'precio' => '10.00']);
        }

        $this->get("/gestion/carta?categoria={$pizzas->id}")
            ->assertSee('Pizza 15')
            ->assertDontSee('Pizza 16')
            ->assertDontSee('Agua')
            ->assertSee("categoria={$pizzas->id}&amp;page=2", false);

        $this->get("/gestion/carta?categoria={$pizzas->id}&page=2")
            ->assertSee('Pizza 17')
            ->assertDontSee('Pizza 15');
    }
}
