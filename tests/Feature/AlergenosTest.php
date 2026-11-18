<?php

/**
 * Pruebas de la gestión del catálogo de alérgenos (RF-17, CU-14).
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
 * Como en CategoriasTest, los datos se crean en la conexión «gestion» y entra
 * una empleada, porque el catálogo lo mantiene cualquier persona del personal.
 */
class AlergenosTest extends TestCase
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
     * Da de alta productos que declaran un alérgeno.
     *
     * @param  Alergeno  $alergeno  alérgeno declarado
     * @param  list<string>  $nombres  nombres de los productos
     * @return void
     */
    private function declararEn(Alergeno $alergeno, array $nombres): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);

        foreach ($nombres as $nombre) {
            Producto::create(['categoria_id' => $pizzas->id, 'nombre' => $nombre, 'precio' => '10.00'])
                ->alergenos()->attach($alergeno);
        }
    }

    /**
     * El listado va por orden alfabético y dice cuántos productos declara cada alérgeno.
     *
     * @return void
     */
    public function test_listado_con_productos_por_alergeno(): void
    {
        $lacteos = Alergeno::create(['nombre' => 'Lácteos', 'descripcion' => 'Leche y sus derivados, incluida la lactosa']);
        Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten y sus derivados']);
        $this->declararEn($lacteos, ['Margherita', 'Quattro formaggi']);

        $this->get('/gestion/carta/alergenos')
            ->assertOk()
            ->assertSeeInOrder(['Gluten', 'Lácteos'])
            ->assertSeeInOrder(['Leche y sus derivados, incluida la lactosa', '2']);
    }

    /**
     * Alta y modificación, con el nombre repetido y la descripción vacía rechazados.
     *
     * @return void
     */
    public function test_alta_modificacion_y_errores(): void
    {
        $this->post('/gestion/carta/alergenos', ['nombre' => 'Sésamo', 'descripcion' => ' Granos de sésamo y productos a base de sésamo '])
            ->assertRedirect('/gestion/carta/alergenos')
            ->assertSessionHas('estado', 'Alérgeno «Sésamo» añadido al catálogo.');

        $sesamo = Alergeno::where('nombre', 'Sésamo')->sole();
        $this->assertSame('Granos de sésamo y productos a base de sésamo', $sesamo->descripcion);

        $this->post('/gestion/carta/alergenos', ['nombre' => 'Sésamo', 'descripcion' => ''])
            ->assertSessionHasErrors([
                'nombre' => 'Ese alérgeno ya está en el catálogo.',
                'descripcion' => 'Escribe la explicación que verá el cliente en la carta.',
            ]);

        $this->put("/gestion/carta/alergenos/{$sesamo->id}", ['nombre' => 'Sésamo', 'descripcion' => 'Semillas de sésamo y sus derivados'])
            ->assertSessionHas('estado', 'Alérgeno «Sésamo» guardado.');
        $this->assertSame('Semillas de sésamo y sus derivados', $sesamo->refresh()->descripcion);
    }

    /**
     * Un alérgeno que no declara ningún producto se borra.
     *
     * @return void
     */
    public function test_borrar_un_alergeno_sin_productos(): void
    {
        $altramuces = Alergeno::create(['nombre' => 'Altramuces', 'descripcion' => 'Altramuces y productos a base de altramuces']);

        $this->delete("/gestion/carta/alergenos/{$altramuces->id}")
            ->assertSessionHas('estado', 'Alérgeno «Altramuces» borrado del catálogo.');

        $this->assertModelMissing($altramuces);
    }

    /**
     * Si lo declaran productos, no se borra y el aviso dice cuáles: hasta tres
     * por su nombre y el resto como un número.
     *
     * @return void
     */
    public function test_no_se_borra_un_alergeno_en_uso(): void
    {
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten y sus derivados']);
        $this->declararEn($gluten, ['Margherita', 'Diavola', 'Tartufo', 'Calzone', 'Focaccia']);

        $this->delete("/gestion/carta/alergenos/{$gluten->id}")
            ->assertSessionHas('error', 'No se puede borrar «Gluten» porque lo declaran estos productos: '
                .'«Calzone», «Diavola», «Focaccia» y 2 más. Quítalo antes de cada producto si de verdad no lo llevan.');

        $this->assertModelExists($gluten);
    }

    /**
     * Con dos productos, el aviso los une con «y».
     *
     * @return void
     */
    public function test_el_aviso_con_dos_productos(): void
    {
        $huevos = Alergeno::create(['nombre' => 'Huevos', 'descripcion' => 'Huevos y productos a base de huevo']);
        $this->declararEn($huevos, ['Tiramisú', 'Carbonara']);

        $this->delete("/gestion/carta/alergenos/{$huevos->id}")
            ->assertSessionHas('error', 'No se puede borrar «Huevos» porque lo declaran estos productos: '
                .'«Carbonara» y «Tiramisú». Quítalo antes de cada producto si de verdad no lo llevan.');
    }
}
