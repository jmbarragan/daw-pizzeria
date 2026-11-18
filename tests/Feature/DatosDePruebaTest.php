<?php

/**
 * Pruebas de los sembradores de datos de prueba.
 */

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Usuario;
use Database\Seeders\PersonalYClientesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Comprueba que «db:seed» carga los mismos datos que el script de datos del
 * diseño (recuentos y totales) y que las fechas quedan desplazadas al día de
 * la instalación, de modo que hoy hay turno y pedidos que atender.
 */
class DatosDePruebaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carga los datos de prueba antes de cada prueba.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Mismos recuentos por tabla que el script de datos del diseño.
     *
     * @return void
     */
    public function test_los_recuentos_son_los_del_diseno(): void
    {
        $esperados = [
            'categorias' => 5, 'alergenos' => 14, 'productos' => 10, 'productos_alergenos' => 16, 'horarios' => 8,
            'usuarios' => 5, 'clientes' => 6, 'pedidos' => 8, 'lineas_pedido' => 10, 'ajustes' => 8, 'bloqueos_ip' => 5,
        ];

        foreach ($esperados as $tabla => $filas) {
            $this->assertSame($filas, DB::table($tabla)->count(), "Filas de {$tabla}");
        }
        $this->assertSame(7, DB::table('pedidos')->distinct()->count('estado'));
    }

    /**
     * Totales y ventas cobradas iguales a los del diseño: dos pedidos de 20,50 €
     * y 31,50 € cobrados en dos pedidos.
     *
     * @return void
     */
    public function test_los_totales_son_los_del_diseno(): void
    {
        $this->assertSame('20.50', (string) DB::table('v_pedidos_totales')->where('pedido_id', 1)->value('total'));
        $this->assertSame('20.50', (string) DB::table('v_pedidos_totales')->where('pedido_id', 6)->value('total'));

        $ventas = DB::table('v_pedidos_totales')->where('estado', 'recogido');
        $this->assertSame('31.50', (string) $ventas->sum('total'));
        $this->assertSame(2, $ventas->count());
    }

    /**
     * La carta pública tiene 9 productos (la Limonada está retirada) y la
     * Tartufo sale agotada.
     *
     * @return void
     */
    public function test_la_carta_publica(): void
    {
        $this->assertSame(9, Producto::enCarta()->count());
        $this->assertSame(['Tartufo'], Producto::enCarta()->where('disponible', false)->pluck('nombre')->all());
    }

    /**
     * Las fechas se desplazan a la instalación: hoy hay turno abierto y los
     * pedidos pendiente, aceptado y listo son de hoy. Los códigos llevan el año
     * de su fecha de recogida.
     *
     * @return void
     */
    public function test_las_fechas_quedan_en_el_dia_de_la_instalacion(): void
    {
        $hoy = now()->toDateString();

        $this->assertTrue(DB::table('horarios')->where('dia', $hoy)->exists());
        $this->assertSame(
            ['aceptado', 'listo', 'pendiente'],
            DB::table('pedidos')->whereIn('estado', ['pendiente', 'aceptado', 'listo'])
                ->where('fecha_prevista', $hoy)->pluck('estado')->sort()->values()->all()
        );
        $this->assertStringEndsWith(now()->format('y'), DB::table('pedidos')->where('id', 7)->value('codigo'));
    }

    /**
     * La administradora de prueba entra con la contraseña de la guía de uso.
     *
     * @return void
     */
    public function test_las_cuentas_de_prueba_aceptan_su_contrasena(): void
    {
        $marta = Usuario::where('email', 'marta.rossi@example.com')->first();

        $this->assertTrue($marta->esAdministrador());
        $this->assertTrue(Auth::getProvider()->validateCredentials($marta, ['password' => PersonalYClientesSeeder::CLAVE_DE_PRUEBA]));
    }
}
