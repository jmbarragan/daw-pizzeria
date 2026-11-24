<?php

/**
 * Pruebas de las reglas que vigila MySQL por su cuenta, sin pasar por la aplicación.
 */

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Comprueba en MySQL las reglas que guarda la propia base de datos: las
 * restricciones CHECK, las claves externas, los disparadores, las vistas, el
 * procedimiento de anonimización y su evento. Son las 25 comprobaciones de
 * integridad que ya pasaban los scripts del diseño, más la vista de totales y
 * el evento.
 *
 * Cada prueba parte de una base recién migrada con unos pocos datos propios
 * (ver prepararDatos()), así que no depende de los sembradores.
 */
class ReglasDeLaBaseDeDatosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepara los datos mínimos de cada prueba.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->prepararDatos();
    }

    /**
     * Sentencias que la base de datos tiene que rechazar, con el fragmento del
     * error que lo demuestra (normalmente el nombre de la regla).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function operacionesProhibidas(): array
    {
        return [
            'pedido aceptado sin hora de recogida' => [
                "UPDATE pedidos SET estado = 'aceptado', usuario_id = 2, decidido_en = NOW() WHERE id = 2",
                'ck_pedidos_recogida',
            ],
            'pedido recogido sin pago' => [
                "UPDATE pedidos SET estado = 'recogido', cerrado_en = NOW() WHERE id = 3",
                'ck_pedidos_pago',
            ],
            'rechazo sin motivo' => [
                "UPDATE pedidos SET estado = 'rechazado', usuario_id = 2, decidido_en = NOW(), cerrado_en = NOW() WHERE id = 2",
                'ck_pedidos_rechazo',
            ],
            'línea con cantidad 0' => [
                "INSERT INTO lineas_pedido VALUES (2, 2, 'Focaccia', 6.00, 0)",
                'ck_lineas_pedido_cantidad',
            ],
            'producto con precio 0' => [
                'UPDATE productos SET precio = 0 WHERE id = 1',
                'ck_productos_precio',
            ],
            'turno que cierra antes de abrir' => [
                "INSERT INTO horarios (dia, turno, hora_apertura, hora_cierre) VALUES ('2026-11-09', 1, '23:00', '20:00')",
                'ck_horarios_horas',
            ],
            'fecha que no existe (31/11)' => [
                "INSERT INTO horarios (dia, turno, hora_apertura, hora_cierre) VALUES ('2026-11-31', 1, '20:00', '23:00')",
                'Incorrect date value',
            ],
            'turno que se solapa con otro del mismo día' => [
                "INSERT INTO horarios (dia, turno, hora_apertura, hora_cierre) VALUES ('2026-11-07', 3, '15:00', '21:00')",
                'se solapa',
            ],
            'número de turno repetido en el mismo día' => [
                "INSERT INTO horarios (dia, turno, hora_apertura, hora_cierre) VALUES ('2026-11-07', 2, '17:00', '18:00')",
                'Duplicate entry',
            ],
            'cuenta de cliente sin correo' => [
                "UPDATE clientes SET contrasena = 'x' WHERE id = 3",
                'ck_clientes_cuenta',
            ],
            'cliente sin teléfono y sin anonimizar' => [
                'UPDATE clientes SET telefono = NULL WHERE id = 2',
                'ck_clientes_telefono',
            ],
            'código de pedido en minúsculas' => [
                "INSERT INTO pedidos (codigo, cliente_id, token_seguimiento, fecha_prevista) VALUES ('7b3e0a27', 1, SHA2('m', 256), '2027-01-03')",
                'ck_pedidos_codigo',
            ],
            'código cuyo año no es el de la fecha prevista' => [
                "INSERT INTO pedidos (codigo, cliente_id, token_seguimiento, fecha_prevista) VALUES ('ABCDEF25', 1, SHA2('a', 256), '2026-11-20')",
                'ck_pedidos_codigo',
            ],
            'pedido sin fecha prevista' => [
                "INSERT INTO pedidos (cliente_id, token_seguimiento) VALUES (1, SHA2('f', 256))",
                'sin fecha prevista',
            ],
            'borrar una categoría con productos' => [
                'DELETE FROM categorias WHERE id = 1',
                'foreign key constraint fails',
            ],
            'borrar un producto con ventas' => [
                'DELETE FROM productos WHERE id = 1',
                'foreign key constraint fails',
            ],
            'borrar un alérgeno asignado' => [
                'DELETE FROM alergenos WHERE id = 1',
                'foreign key constraint fails',
            ],
            'pedido de un cliente que no existe' => [
                "INSERT INTO pedidos (cliente_id, token_seguimiento, fecha_prevista) VALUES (99, SHA2('x', 256), '2026-11-20')",
                'foreign key constraint fails',
            ],
            'código de pedido repetido' => [
                "INSERT INTO pedidos (codigo, cliente_id, token_seguimiento, fecha_prevista)
                 SELECT codigo, 1, SHA2('y', 256), fecha_prevista FROM pedidos WHERE id = 2",
                'Duplicate entry',
            ],
        ];
    }

    /**
     * La base de datos rechaza la sentencia y el error nombra la regla incumplida.
     *
     * @param  string  $sentencia  SQL que debe fallar
     * @param  string  $motivo  fragmento esperado en el mensaje de error
     * @return void
     *
     * @throws QueryException siempre, con el nombre de la regla en el mensaje: es lo que se espera
     */
    #[DataProvider('operacionesProhibidas')]
    public function test_la_base_de_datos_rechaza(string $sentencia, string $motivo): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage($motivo);

        DB::statement($sentencia);
    }

    /**
     * Una fecha sin turnos está cerrada; al añadir uno abre, y el día de la
     * semana sale de la propia fecha (09/11/2026 es lunes).
     *
     * @return void
     */
    public function test_una_fecha_sin_turnos_esta_cerrada_y_abre_al_anadir_uno(): void
    {
        $this->assertSame(0, DB::table('horarios')->where('dia', '2026-11-09')->count());

        DB::table('horarios')->insert(['dia' => '2026-11-09', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:00']);

        $this->assertSame(1, DB::table('horarios')->where('dia', '2026-11-09')->count());
        $this->assertSame(0, (int) DB::scalar("SELECT WEEKDAY('2026-11-09')"));
    }

    /**
     * El disparador genera el código con el año de recogida, no el del alta:
     * un pedido hecho el 31/12/2026 para el 03/01/2027 termina en 27.
     *
     * @return void
     */
    public function test_el_codigo_lleva_el_ano_de_la_fecha_prevista(): void
    {
        $id = DB::table('pedidos')->insertGetId([
            'cliente_id' => 1,
            'token_seguimiento' => hash('sha256', 'g'),
            'creado_en' => '2026-12-31 22:00:00',
            'fecha_prevista' => '2027-01-03',
        ]);

        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}27$/', DB::table('pedidos')->where('id', $id)->value('codigo'));
    }

    /**
     * El primer bloqueo de una IP dura 24 horas y el segundo en 30 días es permanente.
     *
     * @return void
     */
    public function test_el_segundo_bloqueo_de_una_ip_en_30_dias_es_permanente(): void
    {
        DB::table('bloqueos_ip')->insert(['ip' => '203.0.113.7', 'inicio' => '2026-11-01 10:00:00']);
        DB::table('bloqueos_ip')->insert(['ip' => '203.0.113.7', 'inicio' => '2026-11-20 10:00:00']);

        $bloqueos = DB::table('bloqueos_ip')->where('ip', '203.0.113.7')->orderBy('inicio')->get();

        $this->assertSame('2026-11-02 10:00:00', $bloqueos[0]->fin);
        $this->assertNull($bloqueos[1]->fin);
    }

    /**
     * Solo se anonimiza al invitado cuyo último pedido se cerró hace más de 30
     * días, y conserva el nombre. El registrado y el invitado con un pedido en
     * curso no se tocan.
     *
     * @return void
     */
    public function test_la_anonimizacion_solo_afecta_a_invitados_inactivos(): void
    {
        DB::update('UPDATE pedidos SET creado_en = creado_en - INTERVAL 60 DAY, cerrado_en = NOW() - INTERVAL 60 DAY,
                    pagado_en = NOW() - INTERVAL 60 DAY WHERE id = 1');

        DB::statement('CALL anonimizar_invitados()');

        $anonimizados = DB::table('clientes')->whereNotNull('anonimizado_en')->get();
        $this->assertSame([2], $anonimizados->pluck('id')->all());
        $this->assertNull($anonimizados[0]->telefono);
        $this->assertNull($anonimizados[0]->email);
        $this->assertSame('Diego Ruiz', $anonimizados[0]->nombre);
    }

    /**
     * Borrar un producto sin ventas se lleva también sus alérgenos.
     *
     * @return void
     */
    public function test_borrar_un_producto_sin_ventas_borra_sus_alergenos(): void
    {
        DB::table('productos')->where('id', 2)->delete();

        $this->assertSame(0, DB::table('productos_alergenos')->where('producto_id', 2)->count());
    }

    /**
     * Cancelar y aceptar exigen que el pedido siga pendiente, así que si los dos
     * llegan casi a la vez solo vale el primero que MySQL aplica. La prueba los
     * lanza uno detrás de otro, que es el orden en que MySQL los serializa: el
     * segundo no cambia ninguna fila.
     *
     * @return void
     */
    public function test_entre_cancelar_y_aceptar_solo_gana_el_primero(): void
    {
        $cancelados = DB::update("UPDATE pedidos SET estado = 'cancelado', cerrado_en = NOW() WHERE id = 2 AND estado = 'pendiente'");
        $aceptados = DB::update("UPDATE pedidos SET estado = 'aceptado', usuario_id = 2, horario_id = 3, decidido_en = NOW(),
                                 hora_recogida = '2026-11-10 21:00:00' WHERE id = 2 AND estado = 'pendiente'");

        $this->assertSame([1, 0], [$cancelados, $aceptados]);
        $this->assertSame('cancelado', DB::table('pedidos')->where('id', 2)->value('estado'));
    }

    /**
     * La vista de totales suma las líneas con el precio del momento de la venta.
     *
     * @return void
     */
    public function test_la_vista_de_totales_usa_el_precio_de_la_venta(): void
    {
        $pedido = DB::table('v_pedidos_totales')->where('pedido_id', 1)->first();

        $this->assertSame('2', (string) $pedido->unidades);
        $this->assertSame('18.00', (string) $pedido->total);
    }

    /**
     * El evento de anonimización existe, está activo y corre cada día a las 4:00.
     *
     * @return void
     */
    public function test_el_evento_de_anonimizacion_esta_programado(): void
    {
        $evento = DB::selectOne("SELECT STATUS, INTERVAL_VALUE, INTERVAL_FIELD, TIME(STARTS) AS hora
                                 FROM information_schema.EVENTS
                                 WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME = 'ev_anonimizar_invitados'");

        $this->assertNotNull($evento);
        $this->assertSame(['ENABLED', '1', 'DAY', '04:00:00'], [$evento->STATUS, (string) $evento->INTERVAL_VALUE, $evento->INTERVAL_FIELD, $evento->hora]);
    }

    /**
     * Carga una carta, personal, clientes, turnos y tres pedidos: uno recogido
     * (Diego, invitado), uno pendiente (Carla, registrada) y uno listo para
     * recoger (Elena, invitada por teléfono, sin correo). La Margherita tiene
     * ventas; la Focaccia, no.
     *
     * @return void
     *
     * @throws QueryException si alguna inserción incumple una regla de la base de datos; la prueba fallaría antes de empezar
     */
    private function prepararDatos(): void
    {
        DB::table('categorias')->insert([
            ['id' => 1, 'nombre' => 'Pizzas', 'orden' => 1],
            ['id' => 2, 'nombre' => 'Bebidas', 'orden' => 2],
        ]);
        DB::table('alergenos')->insert([
            ['id' => 1, 'nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten'],
            ['id' => 2, 'nombre' => 'Lácteos', 'descripcion' => 'Leche y derivados'],
        ]);
        DB::table('productos')->insert([
            ['id' => 1, 'categoria_id' => 1, 'nombre' => 'Margherita', 'precio' => 9.50],
            ['id' => 2, 'categoria_id' => 1, 'nombre' => 'Focaccia', 'precio' => 6.00],
            ['id' => 3, 'categoria_id' => 2, 'nombre' => 'Agua', 'precio' => 1.50],
        ]);
        DB::table('productos_alergenos')->insert([
            ['producto_id' => 1, 'alergeno_id' => 1],
            ['producto_id' => 1, 'alergeno_id' => 2],
            ['producto_id' => 2, 'alergeno_id' => 1],
        ]);
        DB::table('usuarios')->insert([
            ['id' => 1, 'nombre' => 'Ana Gil', 'email' => 'ana@focaccia.example', 'contrasena' => bcrypt('x'), 'rol' => 'administrador'],
            ['id' => 2, 'nombre' => 'Bruno Sanz', 'email' => 'bruno@focaccia.example', 'contrasena' => bcrypt('x'), 'rol' => 'empleado'],
        ]);
        DB::table('clientes')->insert([
            ['id' => 1, 'nombre' => 'Carla Pons', 'telefono' => '+34100000001', 'email' => 'carla@correo.example', 'contrasena' => bcrypt('x')],
            ['id' => 2, 'nombre' => 'Diego Ruiz', 'telefono' => '+34100000002', 'email' => 'diego@correo.example', 'contrasena' => null],
            ['id' => 3, 'nombre' => 'Elena Mora', 'telefono' => '+34100000003', 'email' => null, 'contrasena' => null],
        ]);
        DB::table('horarios')->insert([
            ['id' => 1, 'dia' => '2026-11-07', 'turno' => 1, 'hora_apertura' => '13:00', 'hora_cierre' => '16:00'],
            ['id' => 2, 'dia' => '2026-11-07', 'turno' => 2, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30'],
            ['id' => 3, 'dia' => '2026-11-10', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30'],
        ]);
        DB::table('pedidos')->insert([
            'id' => 1, 'cliente_id' => 2, 'usuario_id' => 2, 'horario_id' => 2, 'estado' => 'recogido',
            'token_seguimiento' => hash('sha256', 'pedido-1'), 'creado_en' => '2026-11-07 19:00:00',
            'fecha_prevista' => '2026-11-07', 'decidido_en' => '2026-11-07 19:05:00',
            'hora_recogida' => '2026-11-07 20:30:00', 'cerrado_en' => '2026-11-07 20:35:00', 'pagado_en' => '2026-11-07 20:35:00',
        ]);
        DB::table('pedidos')->insert([
            'id' => 2, 'cliente_id' => 1, 'token_seguimiento' => hash('sha256', 'pedido-2'), 'fecha_prevista' => '2026-11-10',
        ]);
        DB::table('pedidos')->insert([
            'id' => 3, 'cliente_id' => 3, 'usuario_id' => 2, 'horario_id' => 3, 'canal' => 'telefono', 'estado' => 'listo',
            'token_seguimiento' => hash('sha256', 'pedido-3'), 'fecha_prevista' => '2026-11-10',
            'decidido_en' => '2026-11-10 20:10:00', 'hora_recogida' => '2026-11-10 21:00:00',
        ]);
        DB::table('lineas_pedido')->insert([
            ['pedido_id' => 1, 'producto_id' => 1, 'nombre_producto' => 'Margherita', 'precio_unitario' => 9.00, 'cantidad' => 2],
            ['pedido_id' => 2, 'producto_id' => 1, 'nombre_producto' => 'Margherita', 'precio_unitario' => 9.50, 'cantidad' => 1],
            ['pedido_id' => 3, 'producto_id' => 1, 'nombre_producto' => 'Margherita', 'precio_unitario' => 9.50, 'cantidad' => 1],
        ]);
    }
}
