<?php

/**
 * Pruebas de la ayuda de las pantallas de carta y horario (RF-09).
 */

namespace Tests\Feature;

use App\Models\Alergeno;
use App\Models\Categoria;
use App\Models\Horario;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cada pantalla del bloque lleva su ayuda desplegable, y cada una explica lo
 * suyo: se comprueba con una frase de su texto.
 */
class AyudaDeLaGestionTest extends TestCase
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
            'nombre' => 'Pablo Gil',
            'email' => 'pablo.gil@focaccia.example',
            'contrasena' => 'Trevi.2026.horno',
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');
    }

    /**
     * Las ocho pantallas (listados y formularios) tienen su ayuda con su propio texto.
     *
     * @return void
     */
    public function test_cada_pantalla_tiene_su_ayuda(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1, 'activa' => true]);
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten']);
        $margherita = Producto::create(['categoria_id' => $pizzas->id, 'nombre' => 'Margherita', 'precio' => '9.50']);
        $turno = Horario::create(['dia' => today(), 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);

        $pantallas = [
            '/gestion/carta' => 'Sale en la carta, pero no se puede pedir',
            "/gestion/carta/productos/{$margherita->id}" => 'como exige el Reglamento (UE) 1169/2011',
            '/gestion/carta/categorias' => 'Solo se puede borrar una categoría vacía',
            "/gestion/carta/categorias/{$pizzas->id}/editar" => 'Dos categorías con el mismo número salen por orden alfabético',
            '/gestion/carta/alergenos' => 'Es el catálogo de los 14 alérgenos',
            "/gestion/carta/alergenos/{$gluten->id}/editar" => 'para que quien tenga una alergia sepa',
            '/gestion/horario' => 'Una fecha sin turnos está cerrada',
            "/gestion/horario/turnos/{$turno->id}/editar" => 'Un turno no puede pasar de medianoche',
        ];

        foreach ($pantallas as $direccion => $frase) {
            $this->get($direccion)
                ->assertOk()
                ->assertSee('<details class="ayuda', false)
                ->assertSee('<summary>Ayuda</summary>', false)
                ->assertSee($frase);
        }
    }
}
