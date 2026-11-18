<?php

/**
 * Pruebas de las plantillas de la zona pública y de gestión y de las páginas de error.
 */

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\TestCase;

/**
 * Comprueba la estructura común de las pantallas: idioma, título y
 * descripción propios, enlace para saltar al contenido, menú público con la
 * cesta, menú de gestión según el rol y páginas de error propias en español.
 */
class PlantillasTest extends TestCase
{
    /**
     * El inicio se sirve en español, con su título, su descripción, el salto al
     * contenido y el menú público con la cesta vacía.
     *
     * @return void
     */
    public function test_el_inicio_usa_la_plantilla_publica(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="es">', false)
            ->assertSee('<title>Pedidos para recoger · La Focaccia di Trevi</title>', false)
            ->assertSee('<meta name="description"', false)
            ->assertSee('Saltar al contenido')
            ->assertSeeInOrder(['Carta', 'Ayuda', 'Acceder', 'Cesta (0)']);
    }

    /**
     * La cesta de la cabecera suma las unidades guardadas en la sesión.
     *
     * @return void
     */
    public function test_la_cabecera_cuenta_los_productos_de_la_cesta(): void
    {
        $this->withSession(['cesta' => [1 => 2, 8 => 1]])
            ->get('/')
            ->assertSee('Cesta (3)');
    }

    /**
     * Una dirección que no existe muestra la página 404 propia, en español.
     *
     * @return void
     */
    public function test_la_pagina_404_es_propia(): void
    {
        $this->get('/no-existe')
            ->assertNotFound()
            ->assertSee('No encontramos esta página');
    }

    /**
     * Las páginas 403 y 500 propias se pintan con la plantilla pública; la de
     * error del servidor no lee la cesta, por si la sesión no está disponible.
     *
     * @return void
     */
    public function test_las_paginas_403_y_500_son_propias(): void
    {
        $this->assertStringContainsString('No tienes permiso para ver esta página', view('errors.403')->render());

        $error = view('errors.500')->render();
        $this->assertStringContainsString('Algo ha fallado en el servidor', $error);
        $this->assertStringNotContainsString('Cesta (', $error);
    }

    /**
     * El menú de gestión solo enseña Usuarios y Ajustes al administrador, y
     * marca la página activa para los lectores de pantalla.
     *
     * @return void
     */
    public function test_el_menu_de_gestion_depende_del_rol(): void
    {
        $empleado = new Usuario(['nombre' => 'Pablo Gil', 'rol' => Usuario::ROL_EMPLEADO]);
        $administradora = new Usuario(['nombre' => 'Marta Rossi', 'rol' => Usuario::ROL_ADMINISTRADOR]);
        $plantilla = '<x-plantilla.gestion titulo="Pedidos" :usuario="$usuario" activa="pedidos">Contenido</x-plantilla.gestion>';

        $this->blade($plantilla, ['usuario' => $empleado])
            ->assertSee('Pedidos')
            ->assertSee('aria-current="page"', false)
            ->assertDontSee('Usuarios')
            ->assertDontSee('Ajustes');

        $this->blade($plantilla, ['usuario' => $administradora])
            ->assertSee('Usuarios')
            ->assertSee('Ajustes')
            ->assertSee('Marta Rossi');
    }
}
