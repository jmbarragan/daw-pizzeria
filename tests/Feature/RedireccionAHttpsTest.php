<?php

/**
 * Pruebas de la redirección a HTTPS de las páginas pedidas por HTTP en el alojamiento.
 */

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Una ruta de prueba del grupo web responde «ok»; no toca la base de datos.
 * APP_URL se cambia en cada prueba con config().
 */
class RedireccionAHttpsTest extends TestCase
{
    /**
     * Registra la ruta de prueba con el grupo de middleware web.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/prueba-https', fn () => 'ok');
    }

    /**
     * Con APP_URL en https, una petición por http se manda a la misma dirección con https.
     *
     * @return void
     */
    public function test_con_app_url_en_https_lo_pedido_por_http_se_redirige(): void
    {
        config(['app.url' => 'https://pizzeria.example']);

        $this->get('http://localhost/prueba-https?pagina=2')
            ->assertStatus(301)
            ->assertRedirect('https://localhost/prueba-https?pagina=2');
    }

    /**
     * Lo que llega por https (directamente o según X-Forwarded-Proto del túnel) se atiende sin redirigir.
     *
     * @return void
     */
    public function test_lo_pedido_por_https_se_atiende(): void
    {
        config(['app.url' => 'https://pizzeria.example', 'app.proxies_de_confianza' => '10.18.51.12']);

        $this->get('https://localhost/prueba-https')->assertOk()->assertSeeText('ok');
        $this->withServerVariables(['REMOTE_ADDR' => '10.18.51.12'])
            ->get('http://localhost/prueba-https', ['X-Forwarded-Proto' => 'https'])
            ->assertOk();
    }

    /**
     * En local APP_URL va con http y no se redirige nada.
     *
     * @return void
     */
    public function test_en_local_no_se_redirige(): void
    {
        config(['app.url' => 'http://localhost:8000']);

        $this->get('http://localhost/prueba-https')->assertOk();
    }
}
