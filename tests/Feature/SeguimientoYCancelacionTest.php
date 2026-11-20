<?php

/**
 * Pruebas de la página de seguimiento y de la cancelación por el cliente (RF-06, RF-07).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Servicios\EnlaceDeSeguimiento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pruebas simulan el martes 24/11/2026 a las 20:40 con un pedido de dos
 * líneas, 2 Margherita y 1 Diavola, que suman 30,00 €.
 */
class SeguimientoYCancelacionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reloj y ajustes públicos del local.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 20:40'));

        foreach (['telefono_local' => '+34100000000', 'direccion_local' => 'Calle Mayor, 12'] as $clave => $valor) {
            DB::table('ajustes')->insert([
                'clave' => $clave,
                'valor' => $valor,
                'publico' => true,
                'descripcion' => $clave,
            ]);
        }
    }

    /**
     * Guarda el pedido 3C9D1F26 con su token y sus dos líneas.
     *
     * @param  string  $estado  pendiente o aceptado (a las 21:45)
     * @return Pedido
     */
    private function pedido(string $estado = 'pendiente'): Pedido
    {
        $creado = Carbon::parse('2026-11-24 20:30:00');
        $datos = [
            'codigo' => '3C9D1F26',
            'cliente_id' => DB::table('clientes')->insertGetId(['nombre' => 'Lucía Fernández', 'telefono' => '+34100123456']),
            'creado_en' => $creado,
            'fecha_prevista' => '2026-11-24',
            'token_seguimiento' => (new EnlaceDeSeguimiento)->calcular('3C9D1F26', $creado),
        ];

        if ($estado === 'aceptado') {
            $datos += $this->aceptacion();
        }

        $pedido = Pedido::forceCreate($datos);
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        foreach ([['Margherita', '9.50', 2], ['Diavola', '11.00', 1]] as [$nombre, $precio, $cantidad]) {
            DB::table('lineas_pedido')->insert([
                'pedido_id' => $pedido->id,
                'producto_id' => DB::table('productos')->insertGetId(['categoria_id' => $categoria, 'nombre' => $nombre, 'precio' => $precio]),
                'nombre_producto' => $nombre,
                'precio_unitario' => $precio,
                'cantidad' => $cantidad,
            ]);
        }

        return $pedido;
    }

    /**
     * Columnas de un pedido aceptado para las 21:45 por una empleada.
     *
     * @return array<string, mixed>
     */
    private function aceptacion(): array
    {
        $usuario = Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
        $turno = Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);

        return [
            'estado' => 'aceptado',
            'usuario_id' => $usuario->id,
            'decidido_en' => '2026-11-24 20:35:00',
            'horario_id' => $turno->id,
            'hora_recogida' => '2026-11-24 21:45:00',
        ];
    }

    /**
     * Aceptado: avance, hora y dirección de recogida, líneas, total y pago en el local; sin cancelar.
     *
     * @return void
     */
    public function test_muestra_el_pedido_aceptado(): void
    {
        $pedido = $this->pedido('aceptado');

        $this->get("/seguimiento/{$pedido->token_seguimiento}")
            ->assertOk()
            ->assertSee('Hecho el 24/11/2026 a las 20:30')
            ->assertSee('aria-current="step">Aceptado', false)
            ->assertSee('<strong>21:45</strong>', false)
            ->assertSee('Calle Mayor, 12')
            ->assertSeeInOrder(['Margherita', '19,00 €', 'Diavola', '11,00 €', 'Total', '30,00 €'])
            ->assertSee('Pago en el local')
            ->assertSee('+34100000000')
            ->assertDontSee('Cancelar pedido');
    }

    /**
     * Pendiente: ofrece cancelar, pero antes pregunta, y preguntar no cambia nada.
     *
     * @return void
     */
    public function test_pide_confirmacion_antes_de_cancelar(): void
    {
        $token = $this->pedido()->token_seguimiento;

        $this->get("/seguimiento/{$token}")->assertSee('Cancelar pedido')->assertSee("/seguimiento/{$token}/cancelar");
        $this->get("/seguimiento/{$token}/cancelar")->assertOk()->assertSee('Sí, cancelar el pedido');

        $this->assertSame('pendiente', Pedido::sole()->estado);
    }

    /**
     * Confirmada, el pedido queda cancelado y cerrado, y ya no se ofrece cancelar.
     *
     * @return void
     */
    public function test_cancelacion_correcta(): void
    {
        $token = $this->pedido()->token_seguimiento;

        $this->post("/seguimiento/{$token}/cancelar")
            ->assertRedirect("/seguimiento/{$token}")
            ->assertSessionHas('estado', 'Pedido 3C9D1F26 cancelado.');

        $pedido = Pedido::sole();
        $this->assertSame(['cancelado', '2026-11-24 20:40'], [$pedido->estado, $pedido->cerrado_en->format('Y-m-d H:i')]);
        $this->get("/seguimiento/{$token}")->assertSee('Cancelado.')->assertDontSee('Cancelar pedido');
    }

    /**
     * Si el local lo acepta mientras el cliente confirma, no se cancela y se le dice a qué teléfono llamar.
     *
     * @return void
     */
    public function test_cancelacion_tardia_informada(): void
    {
        $token = $this->pedido()->token_seguimiento;
        DB::table('pedidos')->update($this->aceptacion());

        $this->post("/seguimiento/{$token}/cancelar")
            ->assertRedirect("/seguimiento/{$token}")
            ->assertSessionHas('error', 'No se ha podido cancelar: el pedido ya está «aceptado». Si necesitas cambiarlo, llámanos al +34100000000.');

        $this->assertSame('aceptado', Pedido::sole()->estado);
        $this->get("/seguimiento/{$token}/cancelar")->assertRedirect("/seguimiento/{$token}");
    }

    /**
     * Con un token alterado no se ve nada ni se cancela nada.
     *
     * @return void
     */
    public function test_un_enlace_no_valido_no_da_datos_ni_cancela(): void
    {
        $bueno = $this->pedido()->token_seguimiento;
        $malo = substr($bueno, 0, 63).($bueno[63] === 'a' ? 'b' : 'a');

        $this->get("/seguimiento/{$malo}/cancelar")->assertNotFound()->assertDontSee('3C9D1F26');
        $this->post("/seguimiento/{$malo}/cancelar")->assertNotFound()->assertDontSee('3C9D1F26');

        $this->assertSame('pendiente', Pedido::sole()->estado);
    }
}
