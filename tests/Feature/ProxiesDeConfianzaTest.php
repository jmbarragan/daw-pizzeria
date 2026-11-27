<?php

/**
 * Pruebas de los proxies de confianza: la IP del cliente y el HTTPS que llegan
 * en las cabeceras X-Forwarded-* cuando la web está detrás del túnel del alojamiento.
 */

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Una ruta de prueba devuelve la IP y el esquema que ve Laravel; no toca la
 * base de datos. La lista de proxies sale de app.proxies_de_confianza.
 */
class ProxiesDeConfianzaTest extends TestCase
{
    /**
     * Dirección del túnel que tiene delante la web.
     *
     * @var string
     */
    private const PROXY = '10.18.51.12';

    /**
     * IP del cliente (rango de documentación) que el túnel pone en X-Forwarded-For.
     *
     * @var string
     */
    private const CLIENTE = '203.0.113.7';

    /**
     * Registra la ruta que enseña la IP y el esquema de la petición.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/prueba-proxy', fn (Request $peticion) => $peticion->ip().' '.$peticion->getScheme());
    }

    /**
     * Si la petición viene del túnel, Laravel toma la IP y el HTTPS de sus cabeceras.
     *
     * @return void
     */
    public function test_detras_del_proxy_de_confianza_se_ve_la_ip_del_cliente_y_el_https(): void
    {
        config(['app.proxies_de_confianza' => '192.0.2.1, '.self::PROXY]);

        $this->pedirDesdeElProxy()->assertSeeText(self::CLIENTE.' https');
    }

    /**
     * Sin proxies de confianza (en local), las cabeceras no cuentan: cualquiera podría falsear su IP.
     *
     * @return void
     */
    public function test_sin_proxies_de_confianza_se_ignoran_las_cabeceras(): void
    {
        config(['app.proxies_de_confianza' => '']);

        $this->pedirDesdeElProxy()->assertSeeText(self::PROXY.' http');
    }

    /**
     * Petición que llega desde la dirección del túnel con las cabeceras que este añade.
     *
     * @return TestResponse<Response>
     */
    private function pedirDesdeElProxy(): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->get('/prueba-proxy', ['X-Forwarded-For' => self::CLIENTE, 'X-Forwarded-Proto' => 'https']);
    }
}
