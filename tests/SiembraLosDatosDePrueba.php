<?php

/**
 * Datos de prueba guardados de verdad para las pruebas que los leen con otra conexión.
 */

namespace Tests;

use Illuminate\Support\Facades\DB;

/**
 * Algunas pantallas leen con un usuario de MySQL distinto del de la prueba
 * (focaccia_informes) o con uno que no puede crear las filas que necesitan
 * (focaccia_gestion no inserta ajustes). Una transacción que se deshace al
 * final no les sirve: no verían nada. Estas pruebas cargan los datos de los
 * sembradores, con la conexión de administración y confirmados, y vacían las
 * tablas al terminar, para que las demás pruebas empiecen de cero. La clase
 * que lo usa tiene que declarar $connectionsToTransact = [].
 */
trait SiembraLosDatosDePrueba
{
    /**
     * Carga los datos de los sembradores.
     *
     * @return void
     */
    protected function sembrar(): void
    {
        $this->seed();
    }

    /**
     * Vacía todas las tablas que llenan los sembradores.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        $admin = DB::connection('admin');
        $admin->statement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['lineas_pedido', 'pedidos', 'clientes', 'horarios', 'productos_alergenos', 'productos', 'alergenos',
            'categorias', 'bloqueos_ip', 'ajustes', 'password_reset_tokens', 'usuarios'] as $tabla) {
            $admin->table($tabla)->truncate();
        }
        $admin->statement('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
    }
}
