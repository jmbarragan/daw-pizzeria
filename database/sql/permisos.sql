-- Roles y permisos de la base de datos. {bd} es la base: la de la aplicación o la de pruebas.
-- Lo aplica «php artisan basedatos:permisos» como root, después de migrar.

CREATE ROLE IF NOT EXISTS 'rol_administrador', 'rol_empleado', 'rol_cliente_web', 'rol_consulta';

-- administrador: todo sobre la base, sin poder dar permisos a otros
GRANT ALL PRIVILEGES ON `{bd}`.* TO 'rol_administrador';

-- gestión: no borra pedidos, líneas, usuarios ni bloqueos (histórico) ni toca la estructura
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.categorias          TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.alergenos           TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.productos           TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.productos_alergenos TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.horarios            TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.clientes            TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE         ON `{bd}`.pedidos             TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE         ON `{bd}`.lineas_pedido       TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE         ON `{bd}`.usuarios            TO 'rol_empleado';
GRANT SELECT, UPDATE                 ON `{bd}`.ajustes             TO 'rol_empleado';
GRANT SELECT, INSERT, UPDATE         ON `{bd}`.bloqueos_ip         TO 'rol_empleado';
GRANT SELECT                         ON `{bd}`.v_pedidos_totales   TO 'rol_empleado';
GRANT SELECT, INSERT, DELETE         ON `{bd}`.password_reset_tokens TO 'rol_empleado';

-- web: lee la carta y crea pedidos; en pedidos solo cambia estado y cerrado_en (cancelar)
GRANT SELECT ON `{bd}`.categorias          TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.alergenos           TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.productos           TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.productos_alergenos TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.horarios            TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.v_ajustes_publicos  TO 'rol_cliente_web';
GRANT SELECT, INSERT, UPDATE ON `{bd}`.clientes TO 'rol_cliente_web';
GRANT SELECT, INSERT ON `{bd}`.pedidos     TO 'rol_cliente_web';
GRANT UPDATE (estado, cerrado_en) ON `{bd}`.pedidos TO 'rol_cliente_web';
GRANT SELECT, INSERT ON `{bd}`.lineas_pedido TO 'rol_cliente_web';
GRANT SELECT, INSERT ON `{bd}`.bloqueos_ip TO 'rol_cliente_web';
GRANT SELECT ON `{bd}`.v_pedidos_totales   TO 'rol_cliente_web';

-- sesiones, caché y colas
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.sessions     TO 'rol_empleado', 'rol_cliente_web';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.cache        TO 'rol_empleado', 'rol_cliente_web';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.cache_locks  TO 'rol_empleado', 'rol_cliente_web';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.jobs         TO 'rol_empleado', 'rol_cliente_web';
GRANT SELECT, INSERT, UPDATE, DELETE ON `{bd}`.job_batches  TO 'rol_empleado', 'rol_cliente_web';
GRANT SELECT, INSERT                 ON `{bd}`.failed_jobs  TO 'rol_empleado', 'rol_cliente_web';

-- consulta: solo lectura, sin contraseñas, tokens ni ajustes
GRANT SELECT ON `{bd}`.categorias          TO 'rol_consulta';
GRANT SELECT ON `{bd}`.alergenos           TO 'rol_consulta';
GRANT SELECT ON `{bd}`.productos           TO 'rol_consulta';
GRANT SELECT ON `{bd}`.productos_alergenos TO 'rol_consulta';
GRANT SELECT ON `{bd}`.horarios            TO 'rol_consulta';
GRANT SELECT ON `{bd}`.lineas_pedido       TO 'rol_consulta';
GRANT SELECT ON `{bd}`.v_pedidos_totales   TO 'rol_consulta';
GRANT SELECT (id, nombre, telefono, email, email_verificado_en, creado_en, anonimizado_en)
    ON `{bd}`.clientes TO 'rol_consulta';
GRANT SELECT (id, nombre, email, rol, activo, creado_en)
    ON `{bd}`.usuarios TO 'rol_consulta';
GRANT SELECT (id, codigo, cliente_id, usuario_id, horario_id, canal, estado,
              observaciones, motivo_rechazo, creado_en, fecha_prevista, decidido_en,
              hora_recogida, cerrado_en, pagado_en)
    ON `{bd}`.pedidos TO 'rol_consulta';
