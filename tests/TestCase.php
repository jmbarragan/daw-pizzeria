<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Base de todas las pruebas de la aplicación.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Las vistas se prueban sin compilar los estilos: @vite no busca el
     * manifiesto de public/build, que solo existe tras «npm run build».
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }
}
