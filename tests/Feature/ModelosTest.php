<?php

/**
 * Pruebas de los modelos Eloquent sobre la base de pruebas.
 */

namespace Tests\Feature;

use App\Models\Ajuste;
use App\Models\Alergeno;
use App\Models\BloqueoIp;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Comprueba que los modelos leen y guardan bien sus tablas: nombres de tabla y
 * de fechas en español, relaciones, conversiones de tipo, contraseñas con hash
 * en la columna contrasena y los filtros de productos en carta y de bloqueos
 * vigentes.
 */
class ModelosTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un producto se guarda con su fecha de alta, pertenece a su categoría y
     * enlaza sus alérgenos a través de productos_alergenos.
     *
     * @return void
     */
    public function test_producto_con_categoria_y_alergenos(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas', 'orden' => 1]);
        $gluten = Alergeno::create(['nombre' => 'Gluten', 'descripcion' => 'Cereales con gluten']);
        $lacteos = Alergeno::create(['nombre' => 'Lácteos', 'descripcion' => 'Leche y derivados']);

        $margherita = $pizzas->productos()->create(['nombre' => 'Margherita', 'precio' => 9.5]);
        $margherita->alergenos()->attach([$gluten->id, $lacteos->id]);

        $leida = Producto::with('categoria', 'alergenos')->find($margherita->id);
        $this->assertSame('Pizzas', $leida->categoria->nombre);
        $this->assertEqualsCanonicalizing(['Gluten', 'Lácteos'], $leida->alergenos->pluck('nombre')->all());
        $this->assertSame('9.50', $leida->precio);
        $this->assertTrue($leida->disponible);
        $this->assertEqualsWithDelta(now()->getTimestamp(), $leida->creado_en->getTimestamp(), 60);
        $this->assertSame(['Margherita'], $gluten->productos->pluck('nombre')->all());
    }

    /**
     * Un producto retirado sigue existiendo pero ya no sale en la carta.
     *
     * @return void
     */
    public function test_los_productos_retirados_no_salen_en_la_carta(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas']);
        $pizzas->productos()->create(['nombre' => 'Margherita', 'precio' => 9.5]);
        $pizzas->productos()->create(['nombre' => 'Hawaiana', 'precio' => 10, 'retirado_en' => now()]);

        $this->assertSame(['Margherita'], Producto::enCarta()->pluck('nombre')->all());
        $this->assertSame(2, Producto::count());
    }

    /**
     * La contraseña del personal se guarda con hash en la columna contrasena,
     * no sale al serializar y no hay token de «recordarme».
     *
     * @return void
     */
    public function test_la_contrasena_del_personal_se_guarda_con_hash(): void
    {
        $ana = Usuario::create([
            'nombre' => 'Ana Gil', 'email' => 'ana@focaccia.example',
            'contrasena' => 'Focaccia.2026', 'rol' => Usuario::ROL_ADMINISTRADOR,
        ]);

        $this->assertTrue(Hash::check('Focaccia.2026', $ana->fresh()->getAuthPassword()));
        $this->assertSame('contrasena', $ana->getAuthPasswordName());
        $this->assertArrayNotHasKey('contrasena', $ana->toArray());
        $ana->setRememberToken('x');
        $this->assertSame('', $ana->getRememberTokenName());
        $this->assertTrue($ana->esAdministrador());
    }

    /**
     * Un cliente sin contraseña es un invitado; con contraseña, registrado.
     *
     * @return void
     */
    public function test_invitado_y_cliente_registrado(): void
    {
        $invitado = Cliente::create(['nombre' => 'Diego Ruiz', 'telefono' => '+34100000002']);
        $registrado = Cliente::create([
            'nombre' => 'Carla Pons', 'telefono' => '+34100000001',
            'email' => 'carla@correo.example', 'contrasena' => 'Focaccia.2026',
        ]);

        $this->assertTrue($invitado->esInvitado());
        $this->assertFalse($registrado->esInvitado());
        $this->assertNotSame('Focaccia.2026', $registrado->fresh()->contrasena);
    }

    /**
     * Al crear un pedido sin código, la base de datos lo genera con el año de
     * recogida; el total es la suma de sus líneas con el precio de la venta.
     *
     * @return void
     */
    public function test_pedido_con_codigo_generado_y_total(): void
    {
        $pizzas = Categoria::create(['nombre' => 'Pizzas']);
        $margherita = $pizzas->productos()->create(['nombre' => 'Margherita', 'precio' => 9.5]);
        $focaccia = $pizzas->productos()->create(['nombre' => 'Focaccia', 'precio' => 6]);
        $cliente = Cliente::create(['nombre' => 'Diego Ruiz', 'telefono' => '+34100000002']);

        $pedido = $cliente->pedidos()->create([
            'token_seguimiento' => hash('sha256', 'prueba'),
            'fecha_prevista' => '2027-01-03',
        ]);
        $pedido->lineas()->createMany([
            ['producto_id' => $margherita->id, 'nombre_producto' => 'Margherita', 'precio_unitario' => 9.00, 'cantidad' => 2],
            ['producto_id' => $focaccia->id, 'nombre_producto' => 'Focaccia', 'precio_unitario' => 6.00, 'cantidad' => 1],
        ]);

        $leido = Pedido::with('lineas', 'cliente')->find($pedido->id);
        $this->assertMatchesRegularExpression('/^[0-9A-F]{6}27$/', $leido->codigo);
        $this->assertSame('pendiente', $leido->estado);
        $this->assertSame('24.00', $leido->total);
        $this->assertSame('Diego Ruiz', $leido->cliente->nombre);
        $this->assertFalse($leido->estaCerrado());
        $this->assertArrayNotHasKey('token_seguimiento', $leido->toArray());
    }

    /**
     * Un pedido aceptado conoce al empleado que lo decidió y su turno de recogida.
     *
     * @return void
     */
    public function test_pedido_aceptado_con_empleado_y_turno(): void
    {
        $bruno = Usuario::create(['nombre' => 'Bruno Sanz', 'email' => 'bruno@focaccia.example', 'contrasena' => 'x']);
        $turno = Horario::create(['dia' => '2026-11-20', 'turno' => 1, 'hora_apertura' => '20:00', 'hora_cierre' => '23:30']);
        $cliente = Cliente::create(['nombre' => 'Elena Mora', 'telefono' => '+34100000003']);

        $pedido = $cliente->pedidos()->create([
            'token_seguimiento' => hash('sha256', 'aceptado'), 'fecha_prevista' => '2026-11-20',
            'estado' => 'aceptado', 'usuario_id' => $bruno->id, 'horario_id' => $turno->id,
            'decidido_en' => '2026-11-20 20:05:00', 'hora_recogida' => '2026-11-20 21:00:00',
        ]);

        $this->assertSame('Bruno Sanz', $pedido->usuario->nombre);
        $this->assertSame('2026-11-20', $pedido->horario->dia->format('Y-m-d'));
        $this->assertSame(1, $bruno->pedidosDecididos()->count());
        $this->assertSame(1, $turno->pedidos()->count());
    }

    /**
     * El ajuste se identifica por su clave de texto y guarda quién lo cambió.
     *
     * @return void
     */
    public function test_ajuste_por_clave(): void
    {
        $ana = Usuario::create(['nombre' => 'Ana Gil', 'email' => 'ana@focaccia.example', 'contrasena' => 'x', 'rol' => 'administrador']);
        Ajuste::create([
            'clave' => 'telefono_local', 'valor' => '+34100000000', 'publico' => true,
            'descripcion' => 'Teléfono que ve el cliente', 'actualizado_por' => $ana->id,
        ]);

        $ajuste = Ajuste::find('telefono_local');
        $this->assertTrue($ajuste->publico);
        $this->assertSame('Ana Gil', $ajuste->actualizadoPor->nombre);
    }

    /**
     * Solo cuenta como vigente el bloqueo sin levantar y sin caducar.
     *
     * @return void
     */
    public function test_bloqueos_vigentes(): void
    {
        BloqueoIp::create(['ip' => '192.0.2.1', 'inicio' => now()->subHour()]);
        BloqueoIp::create(['ip' => '192.0.2.2', 'inicio' => now()->subDays(3)]);
        $levantado = BloqueoIp::create(['ip' => '192.0.2.3', 'inicio' => now()->subHour()]);
        $levantado->update(['desbloqueado_por' => Usuario::create(['nombre' => 'Ana', 'email' => 'a@focaccia.example', 'contrasena' => 'x'])->id,
            'desbloqueado_en' => now()]);

        $this->assertSame(['192.0.2.1'], BloqueoIp::vigentes()->pluck('ip')->all());
    }
}
