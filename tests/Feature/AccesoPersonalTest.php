<?php

/**
 * Pruebas del acceso del personal a la zona de gestión (RF-10, CU-08).
 */

namespace Tests\Feature;

use App\Models\Usuario;
use App\Notifications\RecuperarContrasena;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Las rutas de gestión trabajan con la conexión «gestion», así que la prueba
 * abre la transacción en esa conexión y crea allí sus datos: una conexión no
 * ve lo que otra no ha confirmado.
 */
class AccesoPersonalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Los datos de la prueba se crean con la conexión de gestión.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');
    }

    /**
     * Crea una cuenta del personal.
     *
     * @param  string  $rol  administrador o empleado
     * @param  bool  $activo  falso para una cuenta dada de baja
     * @return Usuario
     */
    private function crearPersonal(string $rol = Usuario::ROL_EMPLEADO, bool $activo = true): Usuario
    {
        return Usuario::create([
            'nombre' => 'Lucía Ferro',
            'email' => "lucia.{$rol}@focaccia.example",
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => $rol,
            'activo' => $activo,
        ]);
    }

    /**
     * Con el correo y la contraseña buenos se entra al panel de pedidos.
     *
     * @return void
     */
    public function test_el_personal_entra_con_sus_credenciales(): void
    {
        $usuario = $this->crearPersonal();

        $this->post('/gestion/acceso', ['email' => $usuario->email, 'contrasena' => $this->contrasenaDePrueba()])
            ->assertRedirect('/gestion/pedidos');

        $this->assertAuthenticatedAs($usuario, 'personal');

        // como en una petición real: se parte de «web» y el usuario se lee de la sesión
        DB::setDefaultConnection('web');
        Auth::forgetGuards();

        $this->get('/gestion/pedidos')->assertOk()->assertSee('Lucía Ferro');
    }

    /**
     * Una contraseña mala da el mismo mensaje que un correo que no existe.
     *
     * @return void
     */
    public function test_el_error_no_dice_que_dato_falla(): void
    {
        $usuario = $this->crearPersonal();

        $this->from('/gestion/acceso')
            ->post('/gestion/acceso', ['email' => $usuario->email, 'contrasena' => 'otra.cosa.1'])
            ->assertRedirect('/gestion/acceso')
            ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);

        $this->post('/gestion/acceso', ['email' => 'nadie@focaccia.example', 'contrasena' => $this->contrasenaDePrueba()])
            ->assertSessionHasErrors(['email' => 'Correo o contraseña incorrectos.']);

        $this->assertGuest('personal');
    }

    /**
     * Una cuenta dada de baja no entra aunque la contraseña sea buena (3c).
     *
     * @return void
     */
    public function test_una_cuenta_de_baja_no_entra(): void
    {
        $usuario = $this->crearPersonal(activo: false);

        $this->post('/gestion/acceso', ['email' => $usuario->email, 'contrasena' => $this->contrasenaDePrueba()])
            ->assertSessionHasErrors('email');

        $this->assertGuest('personal');
    }

    /**
     * Si dan de baja a alguien con la sesión abierta, la pierde en la
     * siguiente petición.
     *
     * @return void
     */
    public function test_una_baja_cierra_la_sesion_abierta(): void
    {
        $usuario = $this->crearPersonal();
        $this->actingAs($usuario, 'personal')->get('/gestion/pedidos')->assertOk();

        $usuario->update(['activo' => false]);

        $this->get('/gestion/pedidos')
            ->assertRedirect('/gestion/acceso')
            ->assertSessionHasErrors('email');
        $this->assertGuest('personal');
    }

    /**
     * Sin sesión, la gestión manda al formulario de acceso.
     *
     * @return void
     */
    public function test_sin_sesion_se_va_al_acceso(): void
    {
        $this->get('/gestion/pedidos')->assertRedirect('/gestion/acceso');
        $this->get('/gestion/acceso')->assertOk()->assertSee('¿Has olvidado la contraseña?');
    }

    /**
     * Usuarios es solo del administrador: el empleado recibe la página 403.
     *
     * @return void
     */
    public function test_usuarios_solo_para_el_administrador(): void
    {
        $this->actingAs($this->crearPersonal(), 'personal')
            ->get('/gestion/usuarios')
            ->assertForbidden()
            ->assertSee('No tienes permiso para ver esta página');

        $this->actingAs($this->crearPersonal(Usuario::ROL_ADMINISTRADOR), 'personal')
            ->get('/gestion/usuarios')
            ->assertOk();
    }

    /**
     * Al cerrar la sesión se vuelve al acceso y la gestión deja de abrirse.
     *
     * @return void
     */
    public function test_cerrar_la_sesion(): void
    {
        $this->actingAs($this->crearPersonal(), 'personal')
            ->post('/gestion/salir')
            ->assertRedirect('/gestion/acceso');

        $this->assertGuest('personal');
    }

    /**
     * Si la dirección es del personal se envía el enlace; si no, la respuesta
     * es la misma. El enlace usa APP_URL aunque la petición llegue con otra
     * cabecera Host.
     *
     * @return void
     */
    public function test_pedir_el_enlace_de_recuperacion(): void
    {
        Notification::fake();
        config(['app.url' => 'https://focaccia.example']);
        $usuario = $this->crearPersonal();
        $aviso = 'Si el correo es del personal, te llegará un enlace para elegir otra contraseña.';

        // por https, como llega con APP_URL en https (por http se redirigiría), y con otro nombre de máquina
        $this->post('https://atacante.example/gestion/contrasena', ['email' => $usuario->email])
            ->assertSessionHas('estado', $aviso);
        $this->post('https://atacante.example/gestion/contrasena', ['email' => 'nadie@focaccia.example'])
            ->assertSessionHas('estado', $aviso);

        Notification::assertSentTo($usuario, RecuperarContrasena::class, function (RecuperarContrasena $correo) use ($usuario) {
            $mensaje = $correo->toMail($usuario);

            return $mensaje->subject === 'Cambia tu contraseña de La Focaccia di Trevi'
                && str_starts_with($mensaje->actionUrl, 'https://focaccia.example/gestion/contrasena/'.$correo->token);
        });
        Notification::assertCount(1);
    }

    /**
     * Con un enlace válido se guarda la contraseña nueva y se entra con ella;
     * con uno inventado, no.
     *
     * @return void
     */
    public function test_elegir_la_contrasena_nueva(): void
    {
        $usuario = $this->crearPersonal();
        $token = Password::broker('personal')->createToken($usuario);
        $nueva = 'Forno.a.legna.7';

        $this->post('/gestion/contrasena/nueva', [
            'token' => 'inventado',
            'email' => $usuario->email,
            'contrasena' => $nueva,
            'contrasena_confirmation' => $nueva,
        ])->assertSessionHasErrors('email');

        $this->post('/gestion/contrasena/nueva', [
            'token' => $token,
            'email' => $usuario->email,
            'contrasena' => $nueva,
            'contrasena_confirmation' => $nueva,
        ])->assertRedirect('/gestion/acceso');

        $this->assertTrue(Hash::check($nueva, $usuario->fresh()->contrasena));
    }
}
