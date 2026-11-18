<?php

/**
 * Orden de instalación que reparte los permisos de MySQL entre los usuarios
 * de la aplicación. Se usa al instalar y cada vez que una migración añade tablas.
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Orden «php artisan basedatos:permisos»: crea los roles de la base de datos,
 * les da los permisos de database/sql/permisos.sql en la base de la aplicación
 * y en la de pruebas, y asigna cada rol a su usuario de MySQL.
 *
 * Se lanza una vez después de migrar, y otra si se añaden tablas: los permisos
 * por tabla solo se pueden conceder sobre tablas que ya existen. Necesita la
 * cuenta root, que solo se usa mientras dura la orden.
 */
class AplicarPermisosBaseDatos extends Command
{
    /**
     * Nombre con el que se lanza la orden desde Artisan; no admite argumentos.
     *
     * @var string
     */
    protected $signature = 'basedatos:permisos';

    /**
     * Texto que muestra «php artisan list» junto a la orden.
     *
     * @var string
     */
    protected $description = 'Crea los roles de MySQL y concede los permisos de cada usuario de la aplicación';

    /**
     * Usuario de MySQL de cada rol: la clave de config/database.php donde están
     * su nombre y su contraseña. Los tres primeros son las conexiones de la
     * aplicación; el de informes no tiene conexión propia.
     *
     * @var array<string, string>
     */
    private const USUARIOS = [
        'rol_administrador' => 'connections.admin',
        'rol_empleado' => 'connections.gestion',
        'rol_cliente_web' => 'connections.web',
        'rol_consulta' => 'instalacion.informes',
    ];

    /**
     * Recorre la base de la aplicación y, si DB_BASE_PRUEBAS está definida, la
     * de pruebas. Un error de MySQL o la falta de la contraseña de root paran
     * la orden con un mensaje; como todo se puede repetir, basta con
     * corregir la causa y volver a lanzarla.
     *
     * @return int Command::SUCCESS si todo se aplicó, Command::FAILURE si no
     */
    public function handle(): int
    {
        try {
            $conexion = $this->conexionRoot();
            $plantilla = file_get_contents(database_path('sql/permisos.sql'));

            $bases = [config('database.connections.admin.database'), config('database.instalacion.base_pruebas')];

            foreach (array_filter($bases) as $base) {
                foreach ($this->sentencias(str_replace('{bd}', $base, $plantilla)) as $sentencia) {
                    $conexion->statement($sentencia);
                }
                $this->info("Permisos aplicados en {$base}");
            }

            $this->asignarRoles($conexion);
        } catch (QueryException|RuntimeException $error) {
            $this->error('No se han podido aplicar los permisos: '.$error->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Abre una conexión con la cuenta root de MySQL (DB_ROOT_USUARIO y
     * DB_ROOT_CONTRASENA, leídas a través de config/database.php).
     *
     * @return Connection
     *
     * @throws RuntimeException si falta la contraseña de root
     */
    private function conexionRoot(): Connection
    {
        $root = config('database.instalacion.root');

        if (! $root['contrasena']) {
            throw new RuntimeException('falta DB_ROOT_CONTRASENA en el fichero .env');
        }

        config(['database.connections.instalacion' => array_merge(config('database.connections.admin'), [
            'username' => $root['usuario'],
            'password' => $root['contrasena'],
        ])]);

        return DB::connection('instalacion');
    }

    /**
     * Separa el fichero SQL en sentencias, sin los comentarios.
     *
     * @param  string  $sql  contenido del fichero con la base ya sustituida
     * @return list<string>
     */
    private function sentencias(string $sql): array
    {
        $sinComentarios = preg_replace('/^\s*--.*$/m', '', $sql);

        return array_values(array_filter(array_map('trim', explode(';', $sinComentarios))));
    }

    /**
     * Crea cada usuario si no existe, le asigna su rol como rol por defecto y
     * le aplica el bloqueo de un día tras cinco intentos fallidos. Un usuario
     * sin contraseña en .env se salta con un aviso.
     *
     * @param  Connection  $conexion  conexión de root
     * @return void
     *
     * @throws QueryException si MySQL rechaza alguna sentencia
     */
    private function asignarRoles(Connection $conexion): void
    {
        $anfitrion = $conexion->getPdo()->quote(config('database.instalacion.anfitrion_usuarios'));

        foreach (self::USUARIOS as $rol => $origen) {
            $datos = config("database.{$origen}");
            $nombre = $datos['usuario'] ?? $datos['username'];
            $contrasena = $datos['contrasena'] ?? $datos['password'];

            if (! $contrasena) {
                $this->warn("Sin contraseña para {$nombre} en .env: no se crea el usuario del rol {$rol}");

                continue;
            }
            $usuario = $conexion->getPdo()->quote($nombre).'@'.$anfitrion;
            $clave = $conexion->getPdo()->quote($contrasena);

            $conexion->statement("CREATE USER IF NOT EXISTS {$usuario} IDENTIFIED BY {$clave}");
            $conexion->statement("GRANT '{$rol}' TO {$usuario}");
            $conexion->statement("SET DEFAULT ROLE '{$rol}' TO {$usuario}");
            $conexion->statement("ALTER USER {$usuario} FAILED_LOGIN_ATTEMPTS 5 PASSWORD_LOCK_TIME 1");
            $this->info("Rol {$rol} asignado a {$usuario}");
        }
    }
}
