<?php

/**
 * Pruebas del rechazo automático de los pedidos pendientes que se han pasado (decisión 030).
 */

namespace Tests\Feature;

use App\Console\Commands\RechazarPedidosPasados;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Notifications\PedidoRechazado;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * La orden trabaja con la conexión «gestion», así que la prueba abre la
 * transacción en esa conexión. Hoy, martes 24/11/2026, hay un turno de 20:00
 * a 23:30.
 */
class RechazoAutomaticoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Conexión de gestión y turno de hoy.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        DB::setDefaultConnection('gestion');
        Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
    }

    /**
     * Guarda un pedido pendiente para ese día.
     *
     * @param  string  $codigo  código de 8 caracteres
     * @param  string  $dia  fecha prevista Y-m-d
     * @return Pedido
     */
    private function pendiente(string $codigo, string $dia): Pedido
    {
        $cliente = DB::table('clientes')->insertGetId(['nombre' => 'Cliente '.$codigo, 'telefono' => '+34100000001']);

        return Pedido::findOrFail(DB::table('pedidos')->insertGetId([
            'codigo' => $codigo,
            'cliente_id' => $cliente,
            'token_seguimiento' => hash('sha256', $codigo),
            'fecha_prevista' => $dia,
        ]));
    }

    /**
     * Lanza la orden a esa hora.
     *
     * @param  string  $cuando  fecha y hora «Y-m-d H:i»
     * @return void
     */
    private function lanzarA(string $cuando): void
    {
        $this->travelTo(Carbon::parse($cuando));
        $this->artisan('pedidos:rechazar-pasados')->assertSuccessful();
    }

    /**
     * Un pendiente de ayer se rechaza con el motivo, en nombre de la cuenta del rechazo automático.
     *
     * @return void
     */
    public function test_rechaza_un_pendiente_de_ayer(): void
    {
        $pedido = $this->pendiente('AAAAAA26', '2026-11-23');

        $this->lanzarA('2026-11-24 10:00');

        $pedido->refresh();
        $this->assertSame(['rechazado', RechazarPedidosPasados::MOTIVO, 'Rechazo automático'], [
            $pedido->estado, $pedido->motivo_rechazo, $pedido->usuario->nombre,
        ]);
        $this->assertNotNull($pedido->cerrado_en);
    }

    /**
     * El cliente recibe el mismo correo de rechazo que cuando rechaza el personal.
     *
     * @return void
     */
    public function test_avisa_al_cliente_por_correo(): void
    {
        Notification::fake();
        $pedido = $this->pendiente('BBBBBB26', '2026-11-23');
        DB::table('clientes')->where('id', $pedido->cliente_id)->update(['email' => 'cliente@example.com']);

        $this->lanzarA('2026-11-24 10:00');

        Notification::assertSentTo($pedido->cliente, PedidoRechazado::class, fn ($aviso) => $aviso->pedido->is($pedido));
    }

    /**
     * Uno de hoy se rechaza en cuanto no le queda ninguna franja, y no antes.
     *
     * @return void
     */
    public function test_uno_de_hoy_al_quedarse_sin_franjas(): void
    {
        $pedido = $this->pendiente('BBBBBB26', '2026-11-24');

        $this->lanzarA('2026-11-24 23:00');
        $this->assertSame('pendiente', $pedido->refresh()->estado);

        // la última franja es la de las 23:15
        $this->lanzarA('2026-11-24 23:16');
        $this->assertSame('rechazado', $pedido->refresh()->estado);
    }

    /**
     * No toca los futuros, los aceptados ni los de un día sin turnos que aún no ha terminado.
     *
     * @return void
     */
    public function test_no_toca_los_que_aun_valen(): void
    {
        $futuro = $this->pendiente('CCCCCC26', '2026-11-26');
        $sinTurnos = $this->pendiente('DDDDDD26', '2026-11-25');
        $aceptado = $this->pendiente('EEEEEE26', '2026-11-23');
        DB::table('pedidos')->where('id', $aceptado->id)->update([
            'estado' => 'aceptado',
            'usuario_id' => Usuario::rechazoAutomatico()->id,
            'decidido_en' => '2026-11-23 20:00:00',
            'horario_id' => Horario::value('id'),
            'hora_recogida' => '2026-11-23 21:00:00',
        ]);

        $this->lanzarA('2026-11-25 22:00');

        $this->assertSame(['pendiente', 'pendiente', 'aceptado'], [
            $futuro->refresh()->estado, $sinTurnos->refresh()->estado, $aceptado->refresh()->estado,
        ]);

        // el de un día sin turnos se rechaza al empezar el siguiente
        $this->lanzarA('2026-11-26 00:15');
        $this->assertSame('rechazado', $sinTurnos->refresh()->estado);
    }

    /**
     * La cuenta del rechazo automático es una sola, está desactivada y no puede iniciar sesión.
     *
     * @return void
     */
    public function test_la_cuenta_del_rechazo_automatico_no_entra(): void
    {
        $this->pendiente('AAAAAA26', '2026-11-23');
        $this->lanzarA('2026-11-24 10:00');
        $this->lanzarA('2026-11-24 10:15');

        $cuenta = Usuario::where('email', Usuario::CORREO_DEL_RECHAZO_AUTOMATICO)->sole();
        $this->assertFalse($cuenta->activo);

        $this->post('/gestion/acceso', ['email' => $cuenta->email, 'contrasena' => 'cualquiera'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('personal');
    }

    /**
     * El planificador la lanza cada 15 minutos.
     *
     * @return void
     */
    public function test_esta_programada_cada_quince_minutos(): void
    {
        $tarea = collect(app(Schedule::class)->events())
            ->first(fn ($evento) => str_contains($evento->command, 'pedidos:rechazar-pasados'));

        $this->assertNotNull($tarea);
        $this->assertSame('*/15 * * * *', $tarea->expression);
    }
}
