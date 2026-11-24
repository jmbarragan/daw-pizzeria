<?php

/**
 * Pruebas de los informes por intervalo de fechas con los datos sembrados (RF-20, CU-17).
 */

namespace Tests\Feature;

use App\Models\Usuario;
use App\Servicios\Informes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\SiembraLosDatosDePrueba;
use Tests\TestCase;

/**
 * Los informes leen con focaccia_informes, otra conexión, que no ve lo que no
 * se ha confirmado: los datos de prueba se cargan de verdad y se vacían al
 * terminar (SiembraLosDatosDePrueba). El reloj está en el 06/11/2026, el día
 * de referencia de los datos sembrados: hay 8 pedidos del 03/11 al 07/11, y
 * solo dos recogidos y cobrados, el 03/11 y el 04/11, que suman 31,50 €.
 */
class InformesTest extends TestCase
{
    use RefreshDatabase;
    use SiembraLosDatosDePrueba;

    /**
     * Ninguna transacción: la conexión de los informes tiene que ver los datos.
     *
     * @var list<string>
     */
    protected $connectionsToTransact = [];

    /**
     * Reloj en el día de referencia, datos de prueba y un empleado identificado.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-06 22:00'));
        $this->sembrar();
        $this->actingAs(Usuario::findOrFail(2), 'personal');
    }

    /**
     * Sin elegir nada, ventas cobradas del mes en curso: dos pedidos, 31,50 €.
     *
     * @return void
     */
    public function test_ventas_cobradas_del_mes(): void
    {
        $this->get('/gestion/informes')->assertOk()
            ->assertSee('Ventas cobradas')
            ->assertSee('del 01/11/2026 al 30/11/2026')
            ->assertSeeInOrder(['martes 3/11/2026', 'miércoles 4/11/2026', 'Total', '2', '31,50 €']);
    }

    /**
     * Los productos de esos dos pedidos suman lo mismo que las ventas.
     *
     * @return void
     */
    public function test_productos_mas_vendidos(): void
    {
        $this->get('/gestion/informes?informe=productos&desde=2026-11-01&hasta=2026-11-30')->assertOk()
            ->assertSee('Productos más vendidos')
            ->assertSeeInOrder(['Total', '31,50 €']);
    }

    /**
     * Por estado cuentan los 8 pedidos, se hayan cobrado o no.
     *
     * @return void
     */
    public function test_pedidos_por_estado(): void
    {
        $this->get('/gestion/informes?informe=estados&desde=2026-11-01&hasta=2026-11-30')->assertOk()
            ->assertSeeInOrder(['Pendiente', 'Aceptado', 'Listo', 'Recogido', 'Rechazado', 'Cancelado', 'No recogido'])
            ->assertSeeInOrder(['Total', '8']);
    }

    /**
     * Un intervalo sin pedidos dice que no hay datos.
     *
     * @return void
     */
    public function test_intervalo_sin_datos(): void
    {
        $this->get('/gestion/informes?informe=ventas&desde=2026-12-01&hasta=2026-12-31')->assertOk()
            ->assertSee('No hay datos para estas fechas.');
    }

    /**
     * El PDF se descarga con su nombre, y su contenido es la misma tabla que la pantalla, con 31,50 €.
     *
     * @return void
     */
    public function test_pdf_de_las_ventas(): void
    {
        $this->get('/gestion/informes?informe=ventas&desde=2026-11-01&hasta=2026-11-30')->assertSee('Descargar PDF');

        $respuesta = $this->get('/gestion/informes/pdf?informe=ventas&desde=2026-11-01&hasta=2026-11-30')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('informe-ventas-2026-11-01-2026-11-30.pdf');
        $this->assertStringStartsWith('%PDF-', $respuesta->getContent());

        // lo que dompdf convierte en PDF: la vista con la tabla compartida
        $html = view('gestion.informe-pdf', [
            'informe' => 'ventas',
            'desde' => Carbon::parse('2026-11-01'),
            'hasta' => Carbon::parse('2026-11-30'),
            'resultado' => app(Informes::class)->calcular('ventas', Carbon::parse('2026-11-01'), Carbon::parse('2026-11-30')),
        ])->render();
        $this->assertStringContainsString('Del 01/11/2026 al 30/11/2026', $html);
        $this->assertMatchesRegularExpression('/Total.*31,50 €/s', $html);
    }

    /**
     * Sin datos no hay PDF: vuelve a la pantalla con el aviso.
     *
     * @return void
     */
    public function test_sin_datos_no_hay_pdf(): void
    {
        $this->get('/gestion/informes?informe=ventas&desde=2026-12-01&hasta=2026-12-31')->assertDontSee('Descargar PDF');

        $this->get('/gestion/informes/pdf?informe=ventas&desde=2026-12-01&hasta=2026-12-31')
            ->assertRedirect('/gestion/informes?informe=ventas&desde=2026-12-01&hasta=2026-12-31')
            ->assertSessionHas('error', 'No hay datos para estas fechas, así que no hay PDF que descargar.');
    }

    /**
     * Una fecha inicial posterior a la final se rechaza.
     *
     * @return void
     */
    public function test_fechas_al_reves(): void
    {
        $this->get('/gestion/informes?informe=ventas&desde=2026-11-30&hasta=2026-11-01')
            ->assertSessionHasErrors(['hasta' => 'La fecha inicial no puede ser posterior a la final.']);
    }
}
