<?php

/**
 * Pruebas de la gestión de clientes y de su anonimización (RF-18, G-06).
 */

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pruebas usan la conexión «gestion», con un empleado identificado, y
 * tres clientes: Lucía, invitada y sin pedidos; Jorge, con cuenta y un pedido
 * cancelado; y Rosa, invitada con un pedido pendiente.
 */
class GestionDeClientesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Conexión, empleado y los tres clientes.
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
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]), 'personal');

        $this->cliente('Lucía Fernández', '+34100111222');
        $jorge = $this->cliente('Jorge Ruiz', '+34100555666', 'jorge@example.com', true);
        $rosa = $this->cliente('Rosa Gil', '+34100777888', 'rosa@example.com');
        $this->pedido($jorge, 'AAAAAA26', 'cancelado');
        $this->pedido($rosa, 'BBBBBB26', 'pendiente');
    }

    /**
     * Guarda un cliente.
     *
     * @param  string  $nombre  nombre del cliente
     * @param  string  $telefono  teléfono E.164
     * @param  string|null  $correo  correo, si lo tiene
     * @param  bool  $conCuenta  verdadero si tiene contraseña
     * @return Cliente
     */
    private function cliente(string $nombre, string $telefono, ?string $correo = null, bool $conCuenta = false): Cliente
    {
        return Cliente::create([
            'nombre' => $nombre,
            'telefono' => $telefono,
            'email' => $correo,
            'contrasena' => $conCuenta ? $this->contrasenaDePrueba() : null,
        ]);
    }

    /**
     * Guarda un pedido del cliente: pendiente, o cancelado y cerrado.
     *
     * @param  Cliente  $cliente  cliente del pedido
     * @param  string  $codigo  código de 8 caracteres
     * @param  string  $estado  pendiente o cancelado
     * @return void
     */
    private function pedido(Cliente $cliente, string $codigo, string $estado): void
    {
        DB::table('pedidos')->insert([
            'codigo' => $codigo,
            'cliente_id' => $cliente->id,
            'token_seguimiento' => hash('sha256', $codigo),
            'fecha_prevista' => '2026-11-24',
            'estado' => $estado,
            'cerrado_en' => $estado === 'cancelado' ? '2026-11-24 20:00:00' : null,
        ]);
    }

    /**
     * El listado tiene a los tres; se busca por parte del nombre o por las cifras del teléfono.
     *
     * @return void
     */
    public function test_listado_y_busqueda(): void
    {
        $this->get('/gestion/clientes')->assertOk()
            ->assertSee('Lucía Fernández')->assertSee('Jorge Ruiz')->assertSee('Rosa Gil')->assertSee('Con cuenta');

        $this->get('/gestion/clientes?buscar=ruiz')->assertSee('Jorge Ruiz')->assertDontSee('Lucía Fernández');
        $this->get('/gestion/clientes?buscar=100 777')->assertSee('Rosa Gil')->assertDontSee('Jorge Ruiz');
        $this->get('/gestion/clientes?buscar=nadie')->assertSee('Ningún cliente coincide con la búsqueda.');
    }

    /**
     * Alta y modificación con el teléfono normalizado; un correo vacío queda como NULL.
     *
     * @return void
     */
    public function test_alta_y_modificacion(): void
    {
        $this->post('/gestion/clientes', ['nombre' => 'Ana Martín', 'telefono' => '100 222 333', 'email' => ''])
            ->assertRedirect('/gestion/clientes')->assertSessionHas('estado', 'Cliente «Ana Martín» creado.');
        $ana = Cliente::where('nombre', 'Ana Martín')->sole();
        $this->assertSame(['+34100222333', null], [$ana->telefono, $ana->email]);

        $this->put("/gestion/clientes/{$ana->id}", ['nombre' => 'Ana Martín Sanz', 'telefono' => '+34100222333', 'email' => 'ana@example.com'])
            ->assertSessionHas('estado');
        $this->assertSame(['Ana Martín Sanz', 'ana@example.com'], [$ana->refresh()->nombre, $ana->email]);
    }

    /**
     * Datos que no valen, y una cuenta a la que se le pone el correo de otra cuenta.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        $this->post('/gestion/clientes', ['nombre' => 'A', 'telefono' => '123', 'email' => 'no-es-correo'])
            ->assertSessionHasErrors(['nombre', 'telefono', 'email']);

        $otra = $this->cliente('Marta León', '+34100999000', 'marta@example.com', true);
        $this->put("/gestion/clientes/{$otra->id}", ['nombre' => 'Marta León', 'telefono' => '+34100999000', 'email' => 'jorge@example.com'])
            ->assertSessionHasErrors(['email' => 'Otra cuenta de cliente ya usa ese correo.']);
    }

    /**
     * Sin pedidos, se borra del todo.
     *
     * @return void
     */
    public function test_borrar_sin_pedidos(): void
    {
        $lucia = Cliente::where('nombre', 'Lucía Fernández')->sole();

        $this->delete("/gestion/clientes/{$lucia->id}")->assertSessionHas('estado', 'Cliente «Lucía Fernández» borrado.');

        $this->assertNull(Cliente::find($lucia->id));
    }

    /**
     * Con los pedidos cerrados, se anonimiza: sin teléfono, correo ni cuenta, con el nombre y sus pedidos.
     *
     * @return void
     */
    public function test_anonimizar_con_pedidos_cerrados(): void
    {
        $jorge = Cliente::where('nombre', 'Jorge Ruiz')->sole();

        $this->delete("/gestion/clientes/{$jorge->id}")->assertSessionHas('estado');

        $jorge->refresh();
        $this->assertSame(['Jorge Ruiz', null, null, null], [$jorge->nombre, $jorge->telefono, $jorge->email, $jorge->getAuthPassword()]);
        $this->assertNotNull($jorge->anonimizado_en);
        $this->assertSame(1, $jorge->pedidos()->count());
        $this->get("/gestion/clientes/{$jorge->id}/editar")->assertSessionHas('error');
        $this->get('/gestion/clientes?buscar=ruiz')->assertSee('Anonimizado');
    }

    /**
     * Con un pedido sin cerrar no se toca, porque hace falta su correo para avisarle.
     *
     * @return void
     */
    public function test_no_se_borra_con_un_pedido_sin_cerrar(): void
    {
        $rosa = Cliente::where('nombre', 'Rosa Gil')->sole();

        $this->delete("/gestion/clientes/{$rosa->id}")
            ->assertSessionHas('error', '«Rosa Gil» tiene un pedido sin cerrar: no se puede borrar hasta que se recoja, se rechace o se cancele.');

        $this->assertSame(['+34100777888', 'rosa@example.com'], [$rosa->refresh()->telefono, $rosa->email]);
        $this->assertNull($rosa->anonimizado_en);
    }
}
