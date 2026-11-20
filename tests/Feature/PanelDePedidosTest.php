<?php

/**
 * Pruebas del panel de pedidos de la gestión (RF-11, pantalla G-02).
 */

namespace Tests\Feature;

use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Las pruebas pasan el martes 24/11/2026 a mediodía, con la conexión «gestion»
 * y una empleada identificada. Cada pedido se crea con una línea, porque el
 * total sale de la vista v_pedidos_totales.
 */
class PanelDePedidosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Conexión cuya transacción se revierte al acabar cada prueba.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = ['gestion'];

    /**
     * Empleada que decide los pedidos aceptados de las pruebas.
     *
     * @var Usuario
     */
    private Usuario $empleada;

    /**
     * Turno del martes por la noche, para los pedidos aceptados.
     *
     * @var Horario
     */
    private Horario $turno;

    /**
     * Producto con el que se arman las líneas.
     *
     * @var int
     */
    private int $producto;

    /**
     * Reloj, conexión, empleada, turno y producto de las pruebas.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00'));
        DB::setDefaultConnection('gestion');

        $this->empleada = Usuario::create([
            'nombre' => 'Elena Navarro',
            'email' => 'elena.navarro@focaccia.example',
            'contrasena' => $this->contrasenaDePrueba(),
            'rol' => Usuario::ROL_EMPLEADO,
            'activo' => true,
        ]);
        $this->actingAs($this->empleada, 'personal');

        $this->turno = Horario::create(['dia' => '2026-11-24', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Pizzas', 'orden' => 1]);
        $this->producto = DB::table('productos')->insertGetId(['categoria_id' => $categoria, 'nombre' => 'Margherita', 'precio' => '9.50']);
    }

    /**
     * Guarda un pedido con una línea de Margherita.
     *
     * @param  string  $codigo  código de 8 caracteres (el año, 26)
     * @param  string  $cliente  nombre del cliente
     * @param  string  $telefono  teléfono en E.164
     * @param  array<string, mixed>  $otros  estado y fechas que cambian
     * @return void
     */
    private function pedido(string $codigo, string $cliente, string $telefono, array $otros = []): void
    {
        $idCliente = DB::table('clientes')->insertGetId(['nombre' => $cliente, 'telefono' => $telefono]);

        $id = DB::table('pedidos')->insertGetId($otros + [
            'codigo' => $codigo,
            'cliente_id' => $idCliente,
            'token_seguimiento' => hash('sha256', $codigo),
            'fecha_prevista' => '2026-11-24',
            'creado_en' => '2026-11-24 11:00:00',
        ]);

        DB::table('lineas_pedido')->insert([
            'pedido_id' => $id,
            'producto_id' => $this->producto,
            'nombre_producto' => 'Margherita',
            'precio_unitario' => '9.50',
            'cantidad' => 2,
        ]);
    }

    /**
     * Datos de un pedido ya aceptado para recoger a esa hora del martes.
     *
     * @param  string  $hora  hora de recogida HH:MM
     * @return array<string, mixed>
     */
    private function aceptado(string $hora): array
    {
        return [
            'estado' => 'aceptado',
            'usuario_id' => $this->empleada->id,
            'horario_id' => $this->turno->id,
            'decidido_en' => '2026-11-24 11:30:00',
            'hora_recogida' => "2026-11-24 {$hora}:00",
        ];
    }

    /**
     * «Pendientes» enseña solo los pendientes, del más antiguo al más nuevo, con su total.
     *
     * @return void
     */
    public function test_pendientes_por_orden_de_llegada(): void
    {
        $this->pedido('BBBBBB26', 'Bruno Sanz', '+34100000002', ['creado_en' => '2026-11-24 11:20:00']);
        $this->pedido('AAAAAA26', 'Ana Martín', '+34100000001', ['creado_en' => '2026-11-24 11:05:00']);
        $this->pedido('CCCCCC26', 'Carla Pons', '+34100000003', $this->aceptado('21:00'));

        $this->get('/gestion/pedidos')
            ->assertOk()
            ->assertSee('Pendientes (2)')
            ->assertSeeInOrder(['AAAAAA26', 'Ana Martín', '19,00 €', 'Pendiente', 'BBBBBB26'])
            ->assertDontSee('CCCCCC26');
    }

    /**
     * «Hoy» reúne todos los estados del día, ordenados por la hora de recogida.
     *
     * @return void
     */
    public function test_hoy_por_hora_de_recogida(): void
    {
        // el de las 22:15 se da de alta antes que el de las 20:30
        $this->pedido('DDDD0026', 'Diego Ruiz', '+34100000004', $this->aceptado('22:15'));
        $this->pedido('CCCC0026', 'Elena Mora', '+34100000005', $this->aceptado('20:30'));
        $this->pedido('EEEE0026', 'Fran Gil', '+34100000006', ['fecha_prevista' => '2026-11-25']);

        $this->get('/gestion/pedidos?vista=hoy')
            ->assertSeeInOrder(['CCCC0026', '20:30', 'DDDD0026', '22:15'])
            ->assertDontSee('EEEE0026');
    }

    /**
     * Se busca por el principio del código, por una parte del teléfono, por el día previsto o por el estado.
     *
     * @return void
     */
    public function test_busqueda_por_cada_criterio(): void
    {
        $this->pedido('7B3E0A26', 'Lucía Fernández', '+34100111222');
        $this->pedido('E07C4A26', 'Jorge Ruiz', '+34100555666', $this->aceptado('21:00'));
        $this->pedido('91F6D326', 'Sofía Herrera', '+34100444555', ['fecha_prevista' => '2026-11-26']);

        $this->get('/gestion/pedidos?codigo=7b3')->assertSee('Lucía Fernández')->assertDontSee('Jorge Ruiz');
        $this->get('/gestion/pedidos?telefono='.urlencode('100 555 666'))->assertSee('Jorge Ruiz')->assertDontSee('Lucía Fernández');
        $this->get('/gestion/pedidos?telefono=444')->assertSee('Sofía Herrera')->assertDontSee('Jorge Ruiz');
        $this->get('/gestion/pedidos?fecha=2026-11-26')->assertSee('Sofía Herrera')->assertDontSee('Lucía Fernández');
        $this->get('/gestion/pedidos?estado=aceptado')->assertSee('Jorge Ruiz')->assertDontSee('Sofía Herrera');
        $this->get('/gestion/pedidos?codigo=FFFF')->assertSee('Ningún pedido coincide con la búsqueda.');
    }

    /**
     * Quince por página, sin perder la búsqueda al cambiar de página.
     *
     * @return void
     */
    public function test_quince_por_pagina(): void
    {
        foreach (range(1, 17) as $n) {
            $this->pedido(sprintf('AA%04d26', $n), "Cliente {$n}", sprintf('+3410000%04d', $n), [
                'creado_en' => sprintf('2026-11-24 10:%02d:00', $n),
            ]);
        }

        $this->get('/gestion/pedidos?estado=pendiente')
            ->assertSee('Cliente 17')->assertDontSee('Cliente 2<', false)
            ->assertSee('estado=pendiente&amp;page=2', false);

        $this->get('/gestion/pedidos?estado=pendiente&page=2')->assertSee('Cliente 1<', false)->assertSee('Cliente 2<', false);
    }
}
