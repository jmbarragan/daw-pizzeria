<?php

/**
 * Clase base de las pruebas de la aplicación.
 */

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

/**
 * Base de todas las pruebas de la aplicación.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Contraseña de las cuentas que crea la prueba en curso.
     *
     * @var string|null
     */
    private ?string $contrasenaDePrueba = null;

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

    /**
     * Contraseña para las cuentas que crea una prueba. Se sortea en cada
     * prueba, así que no queda escrita en el código ni puede confundirse con
     * una contraseña de verdad; dentro de la misma prueba es siempre la misma.
     *
     * @return string
     */
    protected function contrasenaDePrueba(): string
    {
        return $this->contrasenaDePrueba ??= Str::password(16);
    }
}
