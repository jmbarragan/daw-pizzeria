<?php

/**
 * Pruebas de los permisos de MySQL de las conexiones web y gestion, sobre la
 * base de pruebas (focaccia_trevi_pruebas).
 */

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comprueba que cada conexión de la aplicación solo puede hacer lo que le toca
 * (RNF-11): la zona pública entra como focaccia_web y la de gestión como
 * focaccia_gestion. Los permisos los aplica «php artisan basedatos:permisos»
 * sobre la base de pruebas, y MySQL los conserva aunque las pruebas vuelvan a
 * crear las tablas.
 *
 * Una sentencia sin permiso falla con el error 1142 o 1143 de MySQL. Si el
 * error es otro (por ejemplo, una clave externa que no existe), el permiso sí
 * estaba: MySQL comprueba los permisos antes que los datos.
 */
class PermisosDeLaBaseDeDatosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Errores de MySQL que indican falta de permiso sobre una tabla o una columna.
     *
     * @var list<int>
     */
    private const ERRORES_DE_PERMISO = [1142, 1143];

    /**
     * Operaciones que cada conexión tiene que poder hacer.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function operacionesPermitidas(): array
    {
        return [
            'gestión da de alta un producto' => ['gestion', "INSERT INTO productos (categoria_id, nombre, precio) VALUES (1, 'Capricciosa', 12.50)"],
            'gestión acepta un pedido' => ['gestion', "UPDATE pedidos SET estado = 'aceptado', usuario_id = 1, horario_id = 1, decidido_en = NOW(),
                                                   hora_recogida = NOW() WHERE id = 1 AND estado = 'pendiente'"],
            'gestión consulta el personal' => ['gestion', 'SELECT COUNT(*) FROM usuarios'],
            'gestión cambia la política de privacidad' => ['gestion', "UPDATE ajustes SET valor = 'Texto nuevo' WHERE clave = 'politica_privacidad'"],
            'gestión levanta un bloqueo de IP' => ['gestion', 'UPDATE bloqueos_ip SET desbloqueado_por = 1, desbloqueado_en = NOW() WHERE id = 1'],
            'gestión guarda un enlace de recuperación' => ['gestion', "INSERT INTO password_reset_tokens (email, token) VALUES ('ana@focaccia.example', 'x')"],
            'web lee la carta' => ['web', 'SELECT COUNT(*) FROM productos WHERE retirado_en IS NULL'],
            'web registra un cliente' => ['web', "INSERT INTO clientes (nombre, telefono, email) VALUES ('Prueba', '+34100000099', 'prueba@correo.example')"],
            'web registra un pedido' => ['web', "INSERT INTO pedidos (cliente_id, token_seguimiento, fecha_prevista) VALUES (1, SHA2('z', 256), '2026-11-20')"],
            'web registra una línea' => ['web', "INSERT INTO lineas_pedido VALUES (1, 1, 'Margherita', 9.50, 1)"],
            'web cancela un pedido pendiente' => ['web', "UPDATE pedidos SET estado = 'cancelado', cerrado_en = NOW() WHERE id = 1 AND estado = 'pendiente'"],
            'web lee los ajustes públicos' => ['web', 'SELECT COUNT(*) FROM v_ajustes_publicos'],
            'web registra un bloqueo de IP' => ['web', "INSERT INTO bloqueos_ip (ip) VALUES ('192.0.2.10')"],
            'web guarda una sesión' => ['web', "INSERT INTO sessions (id, payload, last_activity) VALUES ('prueba', '', 0)"],
            'informes lee los totales' => ['informes', 'SELECT COUNT(*) FROM v_pedidos_totales'],
            'web guarda un enlace de recuperación de cliente' => ['web', "INSERT INTO recuperacion_clientes (email, token) VALUES ('a@correo.example', 'x')"],
        ];
    }

    /**
     * Operaciones que cada conexión no puede hacer.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function operacionesDenegadas(): array
    {
        return [
            'gestión no borra pedidos' => ['gestion', 'DELETE FROM pedidos WHERE id = 1'],
            'gestión no borra líneas' => ['gestion', 'DELETE FROM lineas_pedido WHERE pedido_id = 1'],
            'gestión no borra usuarios' => ['gestion', 'DELETE FROM usuarios WHERE id = 1'],
            'gestión no borra bloqueos' => ['gestion', 'DELETE FROM bloqueos_ip WHERE id = 1'],
            'gestión no cambia la estructura' => ['gestion', 'ALTER TABLE productos ADD COLUMN x INT'],
            'web no lee los ajustes privados' => ['web', 'SELECT * FROM ajustes'],
            'web no levanta bloqueos' => ['web', 'UPDATE bloqueos_ip SET desbloqueado_en = NOW()'],
            'web no registra pagos' => ['web', 'UPDATE pedidos SET pagado_en = NOW() WHERE id = 1'],
            'web no cambia precios' => ['web', 'UPDATE productos SET precio = 1 WHERE id = 1'],
            'web no lee el personal' => ['web', 'SELECT * FROM usuarios'],
            'web no borra pedidos' => ['web', 'DELETE FROM pedidos WHERE id = 1'],
            'web no toca los enlaces de recuperación' => ['web', 'SELECT * FROM password_reset_tokens'],
            'informes no cambia pedidos' => ['informes', "UPDATE pedidos SET estado = 'cancelado' WHERE id = 1"],
            'informes no lee las contraseñas' => ['informes', 'SELECT contrasena FROM usuarios'],
            'web no cambia un enlace de recuperación de cliente' => ['web', "UPDATE recuperacion_clientes SET token = 'y'"],
        ];
    }

    /**
     * La conexión puede ejecutar la operación: si falla, no es por permisos.
     *
     * @param  string  $conexion  web, gestion o informes
     * @param  string  $sentencia  SQL que debe estar permitido
     * @return void
     */
    #[DataProvider('operacionesPermitidas')]
    public function test_la_conexion_puede(string $conexion, string $sentencia): void
    {
        $error = $this->ejecutar($conexion, $sentencia);

        $this->assertNotContains($error, self::ERRORES_DE_PERMISO, "La conexión {$conexion} no tiene permiso: {$sentencia}");
    }

    /**
     * La conexión no puede ejecutar la operación: MySQL la deniega por permisos.
     *
     * @param  string  $conexion  web, gestion o informes
     * @param  string  $sentencia  SQL que debe estar prohibido
     * @return void
     */
    #[DataProvider('operacionesDenegadas')]
    public function test_la_conexion_no_puede(string $conexion, string $sentencia): void
    {
        $this->assertContains($this->ejecutar($conexion, $sentencia), self::ERRORES_DE_PERMISO, "La conexión {$conexion} sí pudo: {$sentencia}");
    }

    /**
     * Ejecuta la sentencia dentro de una transacción que se deshace al final.
     * Ojo: si un ALTER TABLE prohibido llegara a ejecutarse, MySQL confirmaría
     * la transacción por su cuenta y el cambio se quedaría en la base de
     * pruebas hasta la siguiente ejecución, que la vuelve a crear. La prueba
     * fallaría igualmente, que es lo que importa.
     *
     * @param  string  $conexion  nombre de la conexión de Laravel
     * @param  string  $sentencia  SQL que se prueba
     * @return int|null código de error de MySQL, o null si no hubo error
     */
    private function ejecutar(string $conexion, string $sentencia): ?int
    {
        $bd = DB::connection($conexion);
        $bd->beginTransaction();
        try {
            $bd->statement($sentencia);

            return null;
        } catch (QueryException $error) {
            return (int) ($error->errorInfo[1] ?? 0);
        } finally {
            $bd->rollBack();
        }
    }
}
