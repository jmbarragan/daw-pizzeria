<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calendario y pedidos. Cada fila de horarios es un turno de una fecha (dos
 * filas el mismo día, turno partido; ninguna, cerrado). pedidos guarda el
 * código de 8 caracteres y el token del enlace de seguimiento, pero no el
 * total, que calcula la vista v_pedidos_totales. lineas_pedido copia el
 * nombre y el precio del momento de la venta.
 */
return new class extends Migration
{
    /**
     * Crea horarios, con un turno único por fecha; pedidos, con sus tres
     * claves externas sin cascada y los índices por estado y por cliente; y
     * lineas_pedido, que se borran con su pedido.
     *
     * @return void
     *
     * @throws QueryException si falla alguna tabla o alguno de sus índices
     */
    public function up(): void
    {
        Schema::create('horarios', function (Blueprint $tabla) {
            $tabla->comment('Calendario de apertura');
            $tabla->smallIncrements('id')->comment('Id del turno');
            $tabla->date('dia')->comment('Fecha');
            $tabla->unsignedTinyInteger('turno')->default(1)->comment('Turno del día (1, 2...)');
            $tabla->time('hora_apertura')->comment('Apertura');
            $tabla->time('hora_cierre')->comment('Cierre');
            $tabla->unique(['dia', 'turno'], 'uq_horarios_turno');
        });

        Schema::create('pedidos', function (Blueprint $tabla) {
            $tabla->comment('Pedidos para recoger');
            $tabla->increments('id')->comment('Id interno');
            $tabla->char('codigo', 8)->charset('ascii')->collation('ascii_bin')
                ->comment('Código para el cliente: 6 hex + año (7B3E0A26)');
            $tabla->unsignedInteger('cliente_id')->comment('Cliente');
            $tabla->unsignedSmallInteger('usuario_id')->nullable()->comment('Empleado que lo decide');
            $tabla->unsignedSmallInteger('horario_id')->nullable()->comment('Turno de recogida');
            $tabla->enum('canal', ['web', 'telefono'])->default('web')->comment('Web o teléfono');
            $tabla->enum('estado', ['pendiente', 'aceptado', 'listo', 'recogido', 'rechazado', 'cancelado', 'no_recogido'])
                ->default('pendiente')->comment('Estado');
            $tabla->char('token_seguimiento', 64)->charset('ascii')->collation('ascii_bin')
                ->comment('Token del enlace de seguimiento (HMAC-SHA256)');
            $tabla->string('observaciones', 255)->nullable()->comment('Notas del cliente');
            $tabla->string('motivo_rechazo', 255)->nullable()->comment('Motivo del rechazo');
            $tabla->dateTime('creado_en')->useCurrent()->comment('Fecha del pedido');
            $tabla->date('fecha_prevista')->comment('Día de recogida previsto');
            $tabla->dateTime('decidido_en')->nullable()->comment('Fecha de aceptación o rechazo');
            $tabla->dateTime('hora_recogida')->nullable()->comment('Hora de recogida');
            $tabla->dateTime('cerrado_en')->nullable()->comment('Fecha de cierre');
            $tabla->dateTime('pagado_en')->nullable()->comment('Fecha de pago');
            $tabla->unique('codigo', 'uq_pedidos_codigo');
            $tabla->unique('token_seguimiento', 'uq_pedidos_token');
            // Sin borrado en cascada: el personal, los turnos y los clientes con pedidos no se borran.
            $tabla->foreign('cliente_id', 'fk_pedidos_cliente')->references('id')->on('clientes');
            $tabla->foreign('usuario_id', 'fk_pedidos_usuario')->references('id')->on('usuarios');
            $tabla->foreign('horario_id', 'fk_pedidos_horario')->references('id')->on('horarios');
            $tabla->index(['estado', 'creado_en'], 'ix_pedidos_estado_fecha');
            $tabla->index(['cliente_id', 'cerrado_en'], 'ix_pedidos_cliente_cierre');
        });

        Schema::create('lineas_pedido', function (Blueprint $tabla) {
            $tabla->comment('Productos de cada pedido (N:M)');
            $tabla->unsignedInteger('pedido_id')->comment('Pedido');
            $tabla->unsignedSmallInteger('producto_id')->comment('Producto');
            $tabla->string('nombre_producto', 60)->comment('Nombre al vender');
            $tabla->decimal('precio_unitario', 5, 2)->comment('Precio al vender');
            $tabla->unsignedTinyInteger('cantidad')->comment('Unidades (1 a 20)');
            $tabla->primary(['pedido_id', 'producto_id']);
            $tabla->foreign('pedido_id', 'fk_lineas_pedido_pedido')->references('id')->on('pedidos')->cascadeOnDelete();
            $tabla->foreign('producto_id', 'fk_lineas_pedido_producto')->references('id')->on('productos');
        });
    }

    /**
     * Borra lineas_pedido, pedidos y horarios, en ese orden por las
     * claves externas.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede borrar alguna de las tres
     */
    public function down(): void
    {
        Schema::dropIfExists('lineas_pedido');
        Schema::dropIfExists('pedidos');
        Schema::dropIfExists('horarios');
    }
};
