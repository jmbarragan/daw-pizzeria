<?php

/**
 * Prueba de integración de la carta: productos y alérgenos juntos (RF-16, RF-17).
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
 * Recorre las pantallas como lo haría el personal, sin tocar los modelos entre
 * medias: crea el alérgeno y la pizza desde los formularios, comprueba que el
 * alérgeno queda protegido mientras la pizza lo declara y que se puede borrar
 * cuando deja de declararlo.
 */
class CartaConAlergenosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar la prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Entra un empleado con la conexión de gestión.
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
            'contrasena' => 'Trevi.2026.horno',
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * Del alta al borrado del alérgeno, pasando por el producto que lo declara.
     *
     * @return void
     */
    public function test_un_alergeno_queda_protegido_mientras_lo_declare_un_producto(): void
    {
        $this->post('/gestion/carta/categorias', ['nombre' => 'Focaccias', 'orden' => 1, 'activa' => '1']);
        $this->post('/gestion/carta/alergenos', ['nombre' => 'Sésamo', 'descripcion' => 'Semillas de sésamo y sus derivados']);
        $focaccias = Categoria::where('nombre', 'Focaccias')->sole();
        $sesamo = Alergeno::where('nombre', 'Sésamo')->sole();

        $this->post('/gestion/carta/productos', [
            'nombre' => 'Focaccia con sésamo', 'categoria_id' => $focaccias->id, 'precio' => '6,50',
            'alergenos' => [$sesamo->id], 'disponible' => '1',
        ])->assertSessionHasNoErrors();
        $focaccia = Producto::where('nombre', 'Focaccia con sésamo')->sole();

        // el listado de alérgenos ya lo cuenta y no deja borrarlo
        $this->get('/gestion/carta/alergenos')->assertSeeInOrder(['Sésamo', 'Semillas de sésamo y sus derivados', '1']);
        $this->delete("/gestion/carta/alergenos/{$sesamo->id}")
            ->assertSessionHas('error', 'No se puede borrar «Sésamo» porque lo declaran estos productos: '
                .'«Focaccia con sésamo». Quítalo antes de cada producto si de verdad no lo llevan.');

        // la categoría tampoco se puede borrar mientras tenga la focaccia
        $this->delete("/gestion/carta/categorias/{$focaccias->id}")->assertSessionHas('error');

        // se quita el alérgeno del producto y ya se puede borrar
        $this->put("/gestion/carta/productos/{$focaccia->id}", [
            'nombre' => 'Focaccia con sésamo', 'categoria_id' => $focaccias->id, 'precio' => '6,50', 'disponible' => '1',
        ])->assertSessionHasNoErrors();
        $this->delete("/gestion/carta/alergenos/{$sesamo->id}")
            ->assertSessionHas('estado', 'Alérgeno «Sésamo» borrado del catálogo.');

        $this->assertModelMissing($sesamo);
        $this->assertSame(0, $focaccia->alergenos()->count());
    }
}
