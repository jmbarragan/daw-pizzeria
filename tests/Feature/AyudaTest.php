<?php

/**
 * Pruebas de la ayuda en línea del cliente y de la gestión (RF-09, P-07, CU-21).
 */

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comprueba la página del cliente y su enlace en la cabecera, la página de la
 * gestión según el perfil y que las pantallas de gestión de PANTALLAS, más el
 * acceso, llevan su ayuda desplegable. Con la conexión «gestion», salvo la
 * página del cliente, que usa la de la zona pública.
 */
class AyudaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Pantallas de gestión que se abren sin datos previos.
     *
     * @var list<string>
     */
    private const PANTALLAS = [
        '/gestion/pedidos', '/gestion/pedidos/telefonico', '/gestion/carta', '/gestion/carta/productos/nuevo',
        '/gestion/carta/categorias', '/gestion/carta/categorias/nueva', '/gestion/carta/alergenos',
        '/gestion/carta/alergenos/nuevo', '/gestion/clientes', '/gestion/clientes/nuevo', '/gestion/horario',
        '/gestion/horario/turnos/nuevo', '/gestion/informes?informe=estados', '/gestion/usuarios', '/gestion/usuarios/nuevo',
        '/gestion/ajustes',
    ];

    /**
     * Conexión de gestión.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');
    }

    /**
     * Cuenta activa del personal con el rol dado.
     *
     * @param  string  $rol  administrador o empleado
     * @return Usuario
     */
    private function cuenta(string $rol): Usuario
    {
        return Usuario::create([
            'nombre' => $rol === Usuario::ROL_ADMINISTRADOR ? 'Marta Rossi' : 'Pablo Gil',
            'email' => "{$rol}@focaccia.example",
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => $rol,
            'activo' => true,
        ]);
    }

    /**
     * La ayuda del cliente responde las preguntas de P-07 y está enlazada en la cabecera.
     *
     * @return void
     */
    public function test_ayuda_del_cliente(): void
    {
        // la zona pública lee los ajustes con su propio usuario, como en producción
        DB::setDefaultConnection('web');

        $this->get('/ayuda')->assertOk()
            ->assertSeeInOrder(['¿Cómo hago un pedido?', '¿Cómo sé cuándo recogerlo?', '¿Puedo cancelar mi pedido?', 'Alérgenos e intolerancias'])
            ->assertSee('<details class="ayuda', false);

        $this->get('/carta')->assertSee('href="'.url('/ayuda').'"', false);
    }

    /**
     * El empleado ve la ayuda de su trabajo, no la del administrador, y la tiene en el menú.
     *
     * @return void
     */
    public function test_ayuda_de_la_gestion_para_el_empleado(): void
    {
        $this->actingAs($this->cuenta(Usuario::ROL_EMPLEADO), 'personal')
            ->get('/gestion/ayuda')->assertOk()
            ->assertSee('Atender los pedidos')
            ->assertSee('href="'.url('/gestion/ayuda').'"', false)
            ->assertDontSee('Solo para el administrador');
    }

    /**
     * El administrador ve además la ayuda de usuarios y ajustes.
     *
     * @return void
     */
    public function test_ayuda_de_la_gestion_para_el_administrador(): void
    {
        $this->actingAs($this->cuenta(Usuario::ROL_ADMINISTRADOR), 'personal')
            ->get('/gestion/ayuda')->assertOk()
            ->assertSeeInOrder(['Para todo el personal', 'Solo para el administrador', 'Las cuentas del personal', 'Los ajustes']);
    }

    /**
     * Cada pantalla de gestión tiene su botón de ayuda, también el acceso, antes de entrar.
     *
     * @return void
     */
    public function test_cada_pantalla_de_gestion_tiene_ayuda(): void
    {
        $this->get('/gestion/acceso')->assertSee('<summary>Ayuda</summary>', false);

        $this->actingAs($this->cuenta(Usuario::ROL_ADMINISTRADOR), 'personal');
        foreach (self::PANTALLAS as $pantalla) {
            $this->assertStringContainsString('<summary>Ayuda</summary>', $this->get($pantalla)->assertOk()->getContent(), $pantalla);
        }
    }
}
