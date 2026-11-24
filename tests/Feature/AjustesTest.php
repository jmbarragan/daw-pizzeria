<?php

/**
 * Pruebas de los ajustes del local, del correo saliente y de los bloqueos de IP (RF-21, G-10).
 */

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\BloqueoIp;
use App\Models\Usuario;
use App\Servicios\CorreoSaliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Tests\SiembraLosDatosDePrueba;
use Tests\TestCase;

/**
 * focaccia_gestion solo puede cambiar los ajustes, no crearlos, así que se
 * cargan los datos de prueba de verdad (SiembraLosDatosDePrueba). Marta, la
 * administradora, ha entrado. Los sembrados traen el servidor de correo sin
 * contraseña y cinco bloqueos de IP, uno de ellos permanente y vigente.
 */
class AjustesTest extends TestCase
{
    use RefreshDatabase;
    use SiembraLosDatosDePrueba;

    /**
     * Ninguna transacción: los ajustes tienen que existir para la conexión de gestión.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    /**
     * Reloj en el día de referencia de los datos, datos de prueba y la sesión de Marta.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 22:00'));
        $this->sembrar();
        $this->actingAs(Usuario::findOrFail(1), 'personal');
    }

    /**
     * Datos del formulario que valen, con los cambios dados.
     *
     * @param  array<string, string>  $cambios  campos que cambian
     * @return array<string, string>
     */
    private function formulario(array $cambios = []): array
    {
        return $cambios + [
            'telefono_local' => '100 200 300',
            'direccion_local' => 'Plaza de la Fuente, 3',
            'politica_privacidad' => 'Responsable: La Focaccia di Trevi. Usamos tu teléfono y tu correo solo para tu pedido.',
            'smtp_servidor' => 'smtp.correo.example',
            'smtp_puerto' => '465',
            'smtp_usuario' => 'pedidos@correo.example',
            'smtp_contrasena' => '',
        ];
    }

    /**
     * La pantalla enseña los ajustes, dice que no hay contraseña y no la muestra nunca.
     *
     * @return void
     */
    public function test_la_pantalla_no_muestra_la_contrasena(): void
    {
        $this->get('/gestion/ajustes')->assertOk()
            ->assertSee('value="smtp.gmail.com"', false)
            ->assertSee('No hay ninguna')
            ->assertSee('203.0.113.7')
            ->assertSee('Permanente');

        $this->put('/gestion/ajustes', $this->formulario(['smtp_contrasena' => 'clave-del-correo-1']));
        $this->get('/gestion/ajustes')->assertSee('Hay una guardada. Deja el campo vacío')->assertDontSee('clave-del-correo-1');
    }

    /**
     * Guardar cambia los valores, con quién y cuándo, y la contraseña va cifrada.
     *
     * @return void
     */
    public function test_guardar_los_ajustes(): void
    {
        $this->put('/gestion/ajustes', $this->formulario(['smtp_contrasena' => 'clave-del-correo-1']))
            ->assertRedirect('/gestion/ajustes')->assertSessionHas('estado', 'Ajustes guardados.');

        $this->assertSame('+34100200300', Ajuste::find('telefono_local')->valor);
        $this->assertSame([1, '2026-11-06 22:00'], [
            Ajuste::find('direccion_local')->actualizado_por, Ajuste::find('direccion_local')->actualizado_en->format('Y-m-d H:i'),
        ]);
        $cifrada = Ajuste::find('smtp_contrasena')->valor;
        $this->assertNotSame('clave-del-correo-1', $cifrada);
        $this->assertSame('clave-del-correo-1', Crypt::decryptString($cifrada));

        // vacía, la contraseña que había se queda
        $this->put('/gestion/ajustes', $this->formulario());
        $this->assertSame($cifrada, Ajuste::find('smtp_contrasena')->valor);
    }

    /**
     * Datos que no valen: teléfono, servidor con protocolo, puerto y política demasiado corta.
     *
     * @return void
     */
    public function test_datos_no_validos(): void
    {
        $this->put('/gestion/ajustes', $this->formulario([
            'telefono_local' => '12', 'smtp_servidor' => 'https://smtp.gmail.com', 'smtp_puerto' => '70000', 'politica_privacidad' => 'Corta',
        ]))->assertSessionHasErrors(['telefono_local', 'smtp_servidor', 'smtp_puerto', 'politica_privacidad']);

        $this->assertSame('+34100000000', Ajuste::find('telefono_local')->valor);
    }

    /**
     * Con los cuatro datos, el correo sale por el servidor de los ajustes; sin contraseña, por el del .env.
     *
     * @return void
     */
    public function test_el_correo_sale_por_el_servidor_de_los_ajustes(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'mailpit.local']);
        $correo = app(CorreoSaliente::class);

        $this->assertFalse($correo->aplicar());
        $this->assertSame('mailpit.local', config('mail.mailers.smtp.host'));

        $this->put('/gestion/ajustes', $this->formulario(['smtp_contrasena' => 'clave-del-correo-1']));
        $this->assertTrue($correo->aplicar());
        $this->assertSame(['smtp.correo.example', 465, 'pedidos@correo.example', 'clave-del-correo-1', 'smtps'], [
            config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.username'),
            config('mail.mailers.smtp.password'), config('mail.mailers.smtp.scheme'),
        ]);
    }

    /**
     * Desbloquear apunta quién y cuándo, y no se puede repetir.
     *
     * @return void
     */
    public function test_desbloquear_una_ip(): void
    {
        $bloqueo = BloqueoIp::vigentes()->where('ip', '203.0.113.7')->sole();

        $this->patch("/gestion/ajustes/bloqueos/{$bloqueo->id}/desbloquear")->assertSessionHas('estado', 'IP 203.0.113.7 desbloqueada.');

        $bloqueo->refresh();
        $this->assertSame([1, '2026-11-06 22:00'], [$bloqueo->desbloqueado_por, $bloqueo->desbloqueado_en->format('Y-m-d H:i')]);
        $this->assertFalse(BloqueoIp::vigentes()->where('ip', '203.0.113.7')->exists());
        $this->patch("/gestion/ajustes/bloqueos/{$bloqueo->id}/desbloquear")->assertSessionHas('error');
    }

    /**
     * Un empleado no ve los ajustes ni puede cambiarlos.
     *
     * @return void
     */
    public function test_un_empleado_recibe_403(): void
    {
        $this->actingAs(Usuario::findOrFail(2), 'personal');

        $this->get('/gestion/ajustes')->assertForbidden();
        $this->put('/gestion/ajustes', $this->formulario())->assertForbidden();
        $this->assertSame('Calle Mayor, 12', Ajuste::find('direccion_local')->valor);
    }
}
