<?php

/**
 * Pruebas de la gestión de los usuarios del personal (RF-21, CU-20, G-09).
 */

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\AltaDelPersonal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Con la conexión «gestion», Marta es la única administradora activa y la que
 * entra; Pablo es empleado. También existe la cuenta del rechazo automático.
 */
class GestionDeUsuariosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Conexión, las dos cuentas, la del rechazo automático y la sesión de Marta.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');
        Notification::fake();

        $this->actingAs($this->cuenta('Marta Rossi', Usuario::ROL_ADMINISTRADOR), 'personal');
        $this->cuenta('Pablo Gil', Usuario::ROL_EMPLEADO);
        Usuario::rechazoAutomatico();
    }

    /**
     * Guarda una cuenta activa del personal.
     *
     * @param  string  $nombre  nombre y apellidos
     * @param  string  $rol  administrador o empleado
     * @return Usuario
     */
    private function cuenta(string $nombre, string $rol): Usuario
    {
        return Usuario::create([
            'nombre' => $nombre,
            'email' => strtolower(strtok($nombre, ' ')).'@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => $rol,
            'activo' => true,
        ]);
    }

    /**
     * Cuenta del personal por su nombre; la prueba falla si no hay exactamente una.
     *
     * @param  string  $nombre  nombre y apellidos
     * @return Usuario
     */
    private function cuentaDe(string $nombre): Usuario
    {
        return Usuario::where('nombre', $nombre)->sole();
    }

    /**
     * El listado tiene a las dos personas, no la cuenta automática, y se busca por nombre o correo.
     *
     * @return void
     */
    public function test_listado_y_busqueda(): void
    {
        $this->get('/gestion/usuarios')->assertOk()
            ->assertSee('Marta Rossi')->assertSee('Pablo Gil')->assertDontSee('Rechazo automático');

        // el nombre de Marta sale en la cabecera porque es quien ha entrado: se mira su correo, que solo está en la tabla
        $this->get('/gestion/usuarios?buscar=pablo@')->assertSee('pablo@focaccia.example')->assertDontSee('marta@focaccia.example');
        $this->get('/gestion/usuarios/'.Usuario::rechazoAutomatico()->id.'/editar')->assertNotFound();
    }

    /**
     * El alta crea la cuenta activa y le manda el enlace con un token válido; nadie escribe su contraseña.
     *
     * @return void
     */
    public function test_alta_con_enlace_para_la_contrasena(): void
    {
        $this->post('/gestion/usuarios', ['nombre' => 'Elena Navarro', 'email' => 'Elena@Focaccia.example ', 'rol' => Usuario::ROL_EMPLEADO])
            ->assertRedirect('/gestion/usuarios')->assertSessionHas('estado');

        $elena = $this->cuentaDe('Elena Navarro');
        $this->assertSame(['elena@focaccia.example', true], [$elena->email, $elena->activo]);
        Notification::assertSentTo($elena, AltaDelPersonal::class,
            fn (AltaDelPersonal $aviso) => Password::broker('personal')->tokenExists($elena, $aviso->token));
    }

    /**
     * Un correo que ya es de otra persona del personal no vale.
     *
     * @return void
     */
    public function test_correo_repetido(): void
    {
        $this->post('/gestion/usuarios', ['nombre' => 'Otra Marta', 'email' => 'marta@focaccia.example', 'rol' => Usuario::ROL_EMPLEADO])
            ->assertSessionHasErrors(['email' => 'Ese correo ya es de otra persona del personal.']);
    }

    /**
     * A la única administradora activa no se le quita el rol, y con otra administradora, sí.
     *
     * @return void
     */
    public function test_el_ultimo_administrador_conserva_el_rol(): void
    {
        $marta = $this->cuentaDe('Marta Rossi');
        $datos = ['nombre' => 'Marta Rossi', 'email' => $marta->email, 'rol' => Usuario::ROL_EMPLEADO];

        $this->put("/gestion/usuarios/{$marta->id}", $datos)->assertSessionHas('error');
        $this->assertTrue($marta->refresh()->esAdministrador());

        $this->cuentaDe('Pablo Gil')->update(['rol' => Usuario::ROL_ADMINISTRADOR]);
        $this->put("/gestion/usuarios/{$marta->id}", $datos)->assertSessionHas('estado');
        $this->assertFalse($marta->refresh()->esAdministrador());
    }

    /**
     * Un empleado se desactiva y se vuelve a activar; la propia cuenta no se desactiva.
     *
     * @return void
     */
    public function test_desactivar_y_activar(): void
    {
        $pablo = $this->cuentaDe('Pablo Gil');

        $this->patch("/gestion/usuarios/{$pablo->id}/desactivar")->assertSessionHas('estado', 'Cuenta de «Pablo Gil» desactivada.');
        $this->assertFalse($pablo->refresh()->activo);
        $this->patch("/gestion/usuarios/{$pablo->id}/activar")->assertSessionHas('estado');
        $this->assertTrue($pablo->refresh()->activo);

        $marta = $this->cuentaDe('Marta Rossi');
        $this->patch("/gestion/usuarios/{$marta->id}/desactivar")->assertSessionHas('error');
        $this->assertTrue($marta->refresh()->activo);
    }

    /**
     * Con dos administradoras, una desactiva a la otra; queda una sola activa, que ya no se puede quitar.
     *
     * @return void
     */
    public function test_siempre_queda_un_administrador_activo(): void
    {
        $laura = $this->cuenta('Laura Prieto', Usuario::ROL_ADMINISTRADOR);

        $this->patch("/gestion/usuarios/{$laura->id}/desactivar")->assertSessionHas('estado');

        $activos = Usuario::where('rol', Usuario::ROL_ADMINISTRADOR)->where('activo', true)->pluck('nombre')->all();
        $this->assertSame(['Marta Rossi'], $activos);
        $marta = $this->cuentaDe('Marta Rossi');
        $this->put("/gestion/usuarios/{$marta->id}", ['nombre' => 'Marta Rossi', 'email' => $marta->email, 'rol' => Usuario::ROL_EMPLEADO])
            ->assertSessionHas('error', 'Es el único administrador activo: antes de quitarle el rol, haz administradora a otra persona.');
    }

    /**
     * Un empleado no ve la pantalla ni puede dar de alta a nadie.
     *
     * @return void
     */
    public function test_un_empleado_recibe_403(): void
    {
        $this->actingAs($this->cuentaDe('Pablo Gil'), 'personal');

        $this->get('/gestion/usuarios')->assertForbidden();
        $this->post('/gestion/usuarios', ['nombre' => 'Intruso', 'email' => 'intruso@focaccia.example', 'rol' => Usuario::ROL_ADMINISTRADOR])
            ->assertForbidden();
        $this->assertSame(0, Usuario::where('nombre', 'Intruso')->count());
    }
}
