<?php

/**
 * Pruebas del alta, la edición, el borrado y la retirada de productos (RF-16, CU-14, G-05).
 */

namespace Tests\Feature;

use App\Models\Alergeno;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los datos se crean en la conexión «gestion», con una empleada identificada.
 * Todas las pruebas tienen la categoría Pizzas; lo demás lo crea cada una. Lo
 * que se ve en el listado está en ListadoDeProductosTest.
 */
class ProductosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Categoría en la que caen los productos de las pruebas.
     *
     * @var Categoria
     */
    private Categoria $pizzas;

    /**
     * Empleada identificada y la categoría Pizzas.
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
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');

        $this->pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
    }

    /**
     * Crea un producto de pizzas.
     *
     * @param  string  $nombre  nombre del producto
     * @param  array<string, mixed>  $otros  campos que cambian
     * @return Producto
     */
    private function producto(string $nombre, array $otros = []): Producto
    {
        return Producto::create($otros + ['categoria_id' => $this->pizzas->id, 'nombre' => $nombre, 'precio' => '10.00']);
    }

    /**
     * Registra una venta del producto: un pedido con una línea.
     *
     * @param  Producto  $producto  producto vendido
     * @return void
     */
    private function vender(Producto $producto): void
    {
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Carla Pons', 'telefono' => '+34100000001']);

        // el código del pedido lo pone el disparador de la base de datos
        $pedido = DB::table('pedidos')->insertGetId([
            'cliente_id' => $cliente,
            'token_seguimiento' => hash('sha256', $producto->nombre),
            'fecha_prevista' => now()->toDateString(),
        ]);

        DB::table('lineas_pedido')->insert([
            'pedido_id' => $pedido,
            'producto_id' => $producto->id,
            'nombre_producto' => $producto->nombre,
            'precio_unitario' => $producto->precio,
            'cantidad' => 1,
        ]);
    }

    /**
     * El alta guarda el precio escrito con coma y los alérgenos marcados.
     *
     * @return void
     */
    public function test_alta_con_alergenos_y_precio_con_coma(): void
    {
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten y sus derivados']);
        $lacteos = Alergeno::create(['nombre' => 'Lácteos', 'descripcion' => 'Leche y sus derivados']);

        $this->post('/gestion/carta/productos', [
            'nombre' => 'Quattro formaggi', 'descripcion' => 'Mozzarella, gorgonzola, parmesano y fontina',
            'categoria_id' => $this->pizzas->id, 'precio' => '12,50',
            'alergenos' => [$gluten->id, $lacteos->id], 'disponible' => '1',
        ])->assertRedirect('/gestion/carta')->assertSessionHas('estado', 'Producto «Quattro formaggi» añadido a la carta.');

        $producto = Producto::where('nombre', 'Quattro formaggi')->sole();
        $this->assertSame('12.50', $producto->precio);
        $this->assertTrue($producto->disponible);
        $this->assertEqualsCanonicalizing([$gluten->id, $lacteos->id], $producto->alergenos()->pluck('alergenos.id')->all());
    }

    /**
     * Precio cero, con tres decimales o que no cabe, nombre repetido (aunque esté retirado) y categoría inexistente.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        $this->producto('Diavola', ['retirado_en' => now()]);
        $pizzas = $this->pizzas->id;

        // la Diavola está retirada, pero su nombre sigue ocupado
        $this->post('/gestion/carta/productos', ['nombre' => 'Diavola', 'categoria_id' => $pizzas, 'precio' => '0'])
            ->assertSessionHasErrors([
                'nombre' => 'Ya hay un producto con ese nombre; puede que esté retirado de la carta.',
                'precio' => 'El precio debe ser mayor que 0.',
            ]);

        $this->post('/gestion/carta/productos', ['nombre' => 'Capricciosa', 'categoria_id' => $pizzas, 'precio' => '9,505'])
            ->assertSessionHasErrors(['precio' => 'El precio lleva como mucho dos decimales, por ejemplo 9,50.']);

        $this->post('/gestion/carta/productos', ['nombre' => 'Capricciosa', 'categoria_id' => $pizzas, 'precio' => '1000'])
            ->assertSessionHasErrors(['precio' => 'El precio no puede pasar de 999,99 €.']);

        $this->post('/gestion/carta/productos', ['nombre' => 'Capricciosa', 'categoria_id' => 99, 'precio' => '9'])
            ->assertSessionHasErrors(['categoria_id' => 'Elige una de las categorías de la lista.']);

        $this->assertSame(1, Producto::count());
    }

    /**
     * Al editar se pueden cambiar los alérgenos: quedan solo los marcados. Agotarlo lo deja en la carta.
     *
     * @return void
     */
    public function test_editar_alergenos_y_agotar(): void
    {
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten y sus derivados']);
        $lacteos = Alergeno::create(['nombre' => 'Lácteos', 'descripcion' => 'Leche y sus derivados']);
        $margherita = $this->producto('Margherita');
        $margherita->alergenos()->attach([$gluten->id, $lacteos->id]);

        $this->get("/gestion/carta/productos/{$margherita->id}")->assertOk()->assertSee('value="10,00"', false);

        $this->put("/gestion/carta/productos/{$margherita->id}", [
            'nombre' => 'Margherita', 'categoria_id' => $this->pizzas->id, 'precio' => '9.50',
            'alergenos' => [$gluten->id], 'disponible' => '0',
        ])->assertSessionHas('estado', 'Producto «Margherita» guardado.');

        $margherita->refresh();
        $this->assertSame(['9.50', false, null], [$margherita->precio, $margherita->disponible, $margherita->retirado_en]);
        $this->assertSame([$gluten->id], $margherita->alergenos()->pluck('alergenos.id')->all());
    }

    /**
     * Sin ventas se borra, y sus alérgenos con él.
     *
     * @return void
     */
    public function test_borrar_un_producto_sin_ventas(): void
    {
        $calzone = $this->producto('Calzone');
        $calzone->alergenos()->attach(Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten']));

        $this->delete("/gestion/carta/productos/{$calzone->id}")->assertSessionHas('estado', 'Producto «Calzone» borrado.');

        $this->assertModelMissing($calzone);
        $this->assertSame(0, DB::table('productos_alergenos')->where('producto_id', $calzone->id)->count());
    }

    /**
     * Con ventas se retira en lugar de borrarse, la venta conserva su nombre y
     * su precio, y el producto puede volver a la carta (decisión 026).
     *
     * @return void
     */
    public function test_con_ventas_se_retira_y_puede_volver(): void
    {
        $diavola = $this->producto('Diavola', ['precio' => '11.00']);
        $this->vender($diavola);

        $this->delete("/gestion/carta/productos/{$diavola->id}")
            ->assertSessionHas('estado', '«Diavola» ya se ha vendido, así que se ha retirado de la carta en lugar de borrarse. '
                .'Los pedidos antiguos lo conservan, y puedes recuperarlo con «Volver a la carta».');

        $this->assertNotNull($diavola->refresh()->retirado_en);
        $this->assertSame(1, DB::table('lineas_pedido')->where(['producto_id' => $diavola->id, 'nombre_producto' => 'Diavola'])->count());

        $this->patch("/gestion/carta/productos/{$diavola->id}/volver")
            ->assertSessionHas('estado', '«Diavola» vuelve a estar en la carta.');
        $this->assertNull($diavola->refresh()->retirado_en);
    }
}
