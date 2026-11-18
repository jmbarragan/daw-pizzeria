<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes del local que edita el administrador (política de privacidad,
 * teléfono, servidor de correo) y bloqueos de IP por accesos fallidos
 * (RNF-01). La fecha de fin del bloqueo la pone un disparador: 24 horas o
 * permanente si es el segundo en 30 días.
 */
return new class extends Migration
{
    /**
     * Crea la tabla ajustes, identificada por su clave ASCII, y bloqueos_ip, con un
     * índice por IP e inicio para comprobar rápido si una IP está bloqueada.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede crear alguna de las dos tablas
     */
    public function up(): void
    {
        Schema::create('ajustes', function (Blueprint $tabla) {
            $tabla->comment('Ajustes del local');
            $tabla->string('clave', 40)->charset('ascii')->collation('ascii_bin')->comment('Clave');
            $tabla->text('valor')->nullable()->comment('Valor (contraseñas cifradas)');
            $tabla->boolean('publico')->default(false)->comment('Lo puede leer la web');
            $tabla->string('descripcion', 255)->comment('Ayuda en la pantalla de ajustes');
            $tabla->unsignedSmallInteger('actualizado_por')->nullable()->comment('Último admin que lo cambió');
            $tabla->dateTime('actualizado_en')->nullable()->useCurrentOnUpdate()->comment('Fecha del último cambio');
            $tabla->primary('clave');
            $tabla->foreign('actualizado_por', 'fk_ajustes_usuario')->references('id')->on('usuarios');
        });

        Schema::create('bloqueos_ip', function (Blueprint $tabla) {
            $tabla->comment('Bloqueos de IP');
            $tabla->increments('id')->comment('Id del bloqueo');
            $tabla->string('ip', 45)->charset('ascii')->collation('ascii_bin')->comment('IP (v4 o v6)');
            $tabla->dateTime('inicio')->useCurrent()->comment('Inicio');
            $tabla->dateTime('fin')->nullable()->comment('Fin (NULL = permanente)');
            $tabla->unsignedSmallInteger('desbloqueado_por')->nullable()->comment('Admin que lo levantó');
            $tabla->dateTime('desbloqueado_en')->nullable()->comment('Fecha de desbloqueo');
            $tabla->foreign('desbloqueado_por', 'fk_bloqueos_ip_usuario')->references('id')->on('usuarios');
            $tabla->index(['ip', 'inicio'], 'ix_bloqueos_ip_ip');
        });
    }

    /**
     * Borra bloqueos_ip y ajustes.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede borrar alguna de las dos
     */
    public function down(): void
    {
        Schema::dropIfExists('bloqueos_ip');
        Schema::dropIfExists('ajustes');
    }
};
