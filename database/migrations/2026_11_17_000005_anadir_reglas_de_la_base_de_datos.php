<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Reglas que viven en la propia base de datos y que el constructor de esquemas
 * de Laravel no sabe declarar: las 15 restricciones CHECK, las vistas de
 * totales y de ajustes públicos, los disparadores del código del pedido, de
 * los solapes de turnos y de la duración de los bloqueos, y el procedimiento
 * con su evento nocturno de anonimización. El SQL es el del script de
 * creación entregado con el diseño.
 *
 * Los disparadores y el procedimiento se crean con DB::unprepared porque MySQL
 * no los admite como sentencias preparadas. Necesitan
 * log_bin_trust_function_creators=1, y el evento, event_scheduler=ON.
 */
return new class extends Migration
{
    /**
     * Restricciones CHECK por tabla, con el nombre y la condición del diseño.
     *
     * @var array<string, array<string, string>>
     */
    private array $comprobaciones = [
        'productos' => [
            'ck_productos_precio' => 'precio > 0',
        ],
        'clientes' => [
            // con cuenta hace falta correo
            'ck_clientes_cuenta' => 'contrasena IS NULL OR email IS NOT NULL',
            // sin teléfono solo si está anonimizado
            'ck_clientes_telefono' => 'telefono IS NOT NULL OR anonimizado_en IS NOT NULL',
        ],
        'horarios' => [
            'ck_horarios_turno' => 'turno >= 1',
            'ck_horarios_horas' => 'hora_cierre > hora_apertura',
        ],
        'pedidos' => [
            // 6 hexadecimales en mayúsculas y las 2 cifras del año previsto
            'ck_pedidos_codigo' => "codigo REGEXP '^[0-9A-F]{6}[0-9]{2}$'
                AND RIGHT(codigo, 2) = DATE_FORMAT(fecha_prevista, '%y')",
            // lo decide un empleado, salvo si sigue pendiente o lo canceló el cliente
            'ck_pedidos_decision' => "estado IN ('pendiente', 'cancelado')
                OR (usuario_id IS NOT NULL AND decidido_en IS NOT NULL)",
            // hora y turno de recogida solo si se aceptó
            'ck_pedidos_recogida' => "(estado IN ('aceptado', 'listo', 'recogido', 'no_recogido'))
                = (hora_recogida IS NOT NULL AND horario_id IS NOT NULL)",
            'ck_pedidos_pago' => "(estado = 'recogido') = (pagado_en IS NOT NULL)",
            'ck_pedidos_rechazo' => "(estado = 'rechazado') = (motivo_rechazo IS NOT NULL)",
            'ck_pedidos_cierre' => "(estado IN ('recogido', 'rechazado', 'cancelado', 'no_recogido'))
                = (cerrado_en IS NOT NULL)",
        ],
        'lineas_pedido' => [
            'ck_lineas_pedido_precio' => 'precio_unitario > 0',
            'ck_lineas_pedido_cantidad' => 'cantidad BETWEEN 1 AND 20',
        ],
        'bloqueos_ip' => [
            'ck_bloqueos_ip_fin' => 'fin IS NULL OR fin > inicio',
            'ck_bloqueos_ip_desbloqueo' => '(desbloqueado_por IS NULL) = (desbloqueado_en IS NULL)',
        ],
    ];

    /**
     * Añade las comprobaciones, las vistas, los disparadores, el procedimiento
     * y el evento, en ese orden.
     *
     * @return void
     *
     * @throws QueryException si MySQL rechaza una regla, por ejemplo porque
     *                        faltan los permisos del registro binario
     */
    public function up(): void
    {
        $this->quitarObjetosSinTabla();

        foreach ($this->comprobaciones as $tabla => $reglas) {
            foreach ($reglas as $nombre => $condicion) {
                DB::statement("ALTER TABLE {$tabla} ADD CONSTRAINT {$nombre} CHECK ({$condicion})");
            }
        }

        DB::statement(<<<'SQL'
            CREATE VIEW v_pedidos_totales AS
                SELECT p.id AS pedido_id, p.codigo, p.estado, p.creado_en, p.pagado_en,
                       SUM(l.cantidad) AS unidades,
                       SUM(l.cantidad * l.precio_unitario) AS total
                FROM pedidos AS p
                JOIN lineas_pedido AS l ON l.pedido_id = p.id
                GROUP BY p.id, p.codigo, p.estado, p.creado_en, p.pagado_en
            SQL);

        // Lo que puede leer la web: nunca los datos del servidor de correo.
        DB::statement('CREATE VIEW v_ajustes_publicos AS SELECT clave, valor FROM ajustes WHERE publico');

        // Si el pedido llega sin código, se genera; si los 8 caracteres ya existen, se repite.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_pedidos_codigo
            BEFORE INSERT ON pedidos
            FOR EACH ROW
            BEGIN
                IF NEW.fecha_prevista IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
                        'Pedido sin fecha prevista (hace falta para el año del código)';
                END IF;
                IF NEW.codigo IS NULL THEN
                    REPEAT
                        SET NEW.codigo = CONCAT(HEX(RANDOM_BYTES(3)), DATE_FORMAT(NEW.fecha_prevista, '%y'));
                    UNTIL NOT EXISTS (SELECT 1 FROM pedidos WHERE codigo = NEW.codigo) END REPEAT;
                END IF;
            END
            SQL);

        foreach (['alta' => 'INSERT', 'cambio' => 'UPDATE'] as $sufijo => $operacion) {
            $otroTurno = $operacion === 'UPDATE' ? 'AND id <> OLD.id' : '';
            DB::unprepared(<<<SQL
                CREATE TRIGGER trg_horarios_solape_{$sufijo}
                BEFORE {$operacion} ON horarios
                FOR EACH ROW
                BEGIN
                    IF EXISTS (SELECT 1 FROM horarios
                               WHERE dia = NEW.dia
                                 {$otroTurno}
                                 AND NEW.hora_apertura < hora_cierre
                                 AND hora_apertura < NEW.hora_cierre) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT =
                            'El turno se solapa con otro del mismo día';
                    END IF;
                END
                SQL);
        }

        // 24 horas, o permanente si la misma IP ya se bloqueó en los últimos 30 días.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_bloqueos_ip_duracion
            BEFORE INSERT ON bloqueos_ip
            FOR EACH ROW
            BEGIN
                IF EXISTS (SELECT 1 FROM bloqueos_ip
                           WHERE ip = NEW.ip
                             AND inicio >= NEW.inicio - INTERVAL 30 DAY) THEN
                    SET NEW.fin = NULL;
                ELSE
                    SET NEW.fin = NEW.inicio + INTERVAL 24 HOUR;
                END IF;
            END
            SQL);

        // Invitados sin pedidos abiertos ni cerrados en 30 días: se borran teléfono y correo, no el nombre.
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE anonimizar_invitados()
            BEGIN
                UPDATE clientes AS c
                SET c.telefono = NULL,
                    c.email = NULL,
                    c.anonimizado_en = NOW()
                WHERE c.contrasena IS NULL
                  AND c.anonimizado_en IS NULL
                  AND EXISTS (SELECT 1 FROM pedidos AS p WHERE p.cliente_id = c.id)
                  AND NOT EXISTS (SELECT 1 FROM pedidos AS p
                                  WHERE p.cliente_id = c.id
                                    AND (p.cerrado_en IS NULL OR p.cerrado_en > NOW() - INTERVAL 30 DAY));
            END
            SQL);

        DB::unprepared(<<<'SQL'
            CREATE EVENT ev_anonimizar_invitados
                ON SCHEDULE EVERY 1 DAY
                STARTS CURRENT_DATE + INTERVAL 1 DAY + INTERVAL 4 HOUR
                COMMENT 'Anonimiza invitados a los 30 días'
                DO CALL anonimizar_invitados()
            SQL);
    }

    /**
     * Quita todo lo anterior en orden inverso, de modo que las tablas quedan
     * como las dejan las migraciones que las crean.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede quitar alguna regla
     */
    public function down(): void
    {
        $this->quitarObjetosSinTabla();
        foreach (['trg_bloqueos_ip_duracion', 'trg_horarios_solape_cambio', 'trg_horarios_solape_alta', 'trg_pedidos_codigo'] as $disparador) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$disparador}");
        }

        foreach (array_reverse($this->comprobaciones, true) as $tabla => $reglas) {
            foreach (array_keys($reglas) as $nombre) {
                DB::statement("ALTER TABLE {$tabla} DROP CHECK {$nombre}");
            }
        }
    }

    /**
     * Quita el evento, el procedimiento y las vistas. migrate:fresh borra las
     * tablas (y con ellas sus disparadores), pero estos tres objetos no
     * dependen de ninguna tabla y sobreviven; sin quitarlos, la siguiente
     * instalación fallaría al crearlos de nuevo.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede borrar alguno
     */
    private function quitarObjetosSinTabla(): void
    {
        DB::unprepared('DROP EVENT IF EXISTS ev_anonimizar_invitados');
        DB::unprepared('DROP PROCEDURE IF EXISTS anonimizar_invitados');
        DB::statement('DROP VIEW IF EXISTS v_ajustes_publicos');
        DB::statement('DROP VIEW IF EXISTS v_pedidos_totales');
    }
};
