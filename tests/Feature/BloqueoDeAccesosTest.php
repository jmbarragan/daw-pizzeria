<?php

/**
 * Pruebas del límite de intentos y del bloqueo de IP en el acceso del personal (RNF-01).
 */

namespace Tests\Feature;

use App\Models\BloqueoIp;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Como en AccesoPersonalTest, los datos se crean en la conexión «gestion». Las
 * peticiones llegan desde una IP de documentación (203.0.113.0/24) y la prueba
 * adelanta el reloj para no chocar con el límite de cinco por minuto.
 */
class BloqueoDeAccesosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * IP desde la que se hacen los intentos.
     *
     * @var string
     */
    private const IP = '203.0.113.20';

    /**
     * Contraseña buena de la cuenta de prueba.
     *
     * @var string
     */
    private const CONTRASENA = 'Trevi.2026.horno';

    /**
     * Cuenta del personal con la que se prueba.
     *
     * @var Usuario
     */
    private Usuario $usuario;

    /**
     * Crea la cuenta en la conexión de gestión y fija la IP de las peticiones.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');
        $this->withServerVariables(['REMOTE_ADDR' => self::IP]);

        $this->usuario = Usuario::create([
            'nombre' => 'Lucía Ferro',
            'email' => 'lucia.ferro@focaccia.example',
            'contrasena' => self::CONTRASENA,
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
    }

    /**
     * Envía el formulario de acceso.
     *
     * @param  string  $contrasena  contraseña que se prueba
     * @return TestResponse
     */
    private function entrar(string $contrasena): TestResponse
    {
        return $this->from('/gestion/acceso')
            ->post('/gestion/acceso', ['email' => $this->usuario->email, 'contrasena' => $contrasena]);
    }

    /**
     * Falla el acceso varias veces, de cinco en cinco por minuto.
     *
     * @param  int  $veces  número de fallos
     * @return void
     */
    private function fallar(int $veces): void
    {
        for ($i = 1; $i <= $veces; $i++) {
            $this->entrar('no.es.esta.1');

            if ($i % 5 === 0) {
                $this->travel(61)->seconds();
            }
        }
    }

    /**
     * El sexto intento del mismo minuto se rechaza aunque la contraseña sea buena.
     *
     * @return void
     */
    public function test_el_sexto_intento_del_minuto_se_rechaza(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->entrar('no.es.esta.1');
        }

        $this->entrar(self::CONTRASENA)
            ->assertSessionHasErrors(['email' => 'Demasiados intentos seguidos. Espera un minuto y vuelve a probar.']);
        $this->assertGuest('personal');

        $this->travel(61)->seconds();
        $this->entrar(self::CONTRASENA)->assertRedirect('/gestion/pedidos');
    }

    /**
     * Diez fallos bloquean la IP 24 horas: ni siquiera la contraseña buena entra
     * y el acceso explica hasta cuándo. Pasado ese tiempo se puede entrar.
     *
     * @return void
     */
    public function test_diez_fallos_bloquean_la_ip_un_dia(): void
    {
        $this->fallar(10);

        $bloqueo = BloqueoIp::where('ip', self::IP)->sole();
        $this->assertEquals(24, $bloqueo->inicio->diffInHours($bloqueo->fin));

        $this->get('/gestion/acceso')
            ->assertForbidden()
            ->assertSee('Podrás volver a intentarlo el '.$bloqueo->fin->format('d/m/Y'));
        $this->entrar(self::CONTRASENA)->assertForbidden();
        $this->assertGuest('personal');

        $this->travel(25)->hours();
        $this->entrar(self::CONTRASENA)->assertRedirect('/gestion/pedidos');
    }

    /**
     * Si la IP ya se bloqueó en los últimos 30 días, el nuevo bloqueo es permanente.
     *
     * @return void
     */
    public function test_el_segundo_bloqueo_en_30_dias_es_permanente(): void
    {
        BloqueoIp::create(['ip' => self::IP, 'inicio' => now()->subDays(10)]);

        $this->fallar(10);

        $this->assertNull(BloqueoIp::where('ip', self::IP)->latest('inicio')->first()->fin);
        $this->get('/gestion/acceso')
            ->assertForbidden()
            ->assertSee('pide al administrador de la pizzería que lo levante');
    }

    /**
     * Un acceso correcto pone a cero la cuenta: nueve fallos, una entrada y
     * otro fallo no bloquean.
     *
     * @return void
     */
    public function test_un_acceso_correcto_reinicia_la_cuenta(): void
    {
        $this->fallar(9);
        $this->travel(61)->seconds();

        $this->entrar(self::CONTRASENA)->assertRedirect('/gestion/pedidos');
        $this->post('/gestion/salir');
        $this->fallar(1);

        $this->assertSame(0, BloqueoIp::where('ip', self::IP)->count());
        $this->get('/gestion/acceso')->assertOk();
    }

    /**
     * Entrar con otra cuenta no borra los fallos contra la primera: quien
     * tenga una cuenta propia no puede usarla para seguir probando
     * contraseñas ajenas sin llegar al bloqueo.
     *
     * @return void
     */
    public function test_entrar_con_otra_cuenta_no_borra_los_fallos(): void
    {
        $propia = Usuario::create([
            'nombre' => 'Pablo Gil',
            'email' => 'pablo.gil@focaccia.example',
            'contrasena' => self::CONTRASENA,
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);

        $this->fallar(9);
        $this->travel(61)->seconds();

        $this->from('/gestion/acceso')
            ->post('/gestion/acceso', ['email' => $propia->email, 'contrasena' => self::CONTRASENA])
            ->assertRedirect('/gestion/pedidos');
        $this->post('/gestion/salir');
        $this->fallar(1);

        $this->assertSame(1, BloqueoIp::where('ip', self::IP)->count());
        $this->get('/gestion/acceso')->assertForbidden();
    }

    /**
     * Los fallos de la cuenta propia caducan con los de la IP: no se pueden
     * guardar de una ventana de 24 horas para restarlos en la siguiente.
     *
     * @return void
     */
    public function test_los_fallos_propios_no_pasan_a_la_ventana_siguiente(): void
    {
        $propia = Usuario::create([
            'nombre' => 'Pablo Gil',
            'email' => 'pablo.gil@focaccia.example',
            'contrasena' => self::CONTRASENA,
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
        $fallarPropia = function (int $veces) use ($propia) {
            for ($i = 1; $i <= $veces; $i++) {
                $this->from('/gestion/acceso')
                    ->post('/gestion/acceso', ['email' => $propia->email, 'contrasena' => 'no.es.esta.1']);
                if ($i % 5 === 0) {
                    $this->travel(61)->seconds();
                }
            }
        };

        // un fallo abre la ventana; al final de ella, ocho con la cuenta propia
        $this->fallar(1);
        $this->travel(23)->hours();
        $fallarPropia(8);
        $this->travel(2)->hours();

        // en la ventana nueva: nueve fallos ajenos, entrada propia y uno más
        $this->fallar(9);
        $this->travel(61)->seconds();
        $this->from('/gestion/acceso')
            ->post('/gestion/acceso', ['email' => $propia->email, 'contrasena' => self::CONTRASENA])
            ->assertRedirect('/gestion/pedidos');
        $this->post('/gestion/salir');
        $this->fallar(1);

        $this->assertSame(1, BloqueoIp::where('ip', self::IP)->count());
    }

    /**
     * El bloqueo es de la IP: desde otra se sigue entrando.
     *
     * @return void
     */
    public function test_el_bloqueo_solo_afecta_a_esa_ip(): void
    {
        $this->fallar(10);

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21']);
        $this->entrar(self::CONTRASENA)->assertRedirect('/gestion/pedidos');
    }
}
