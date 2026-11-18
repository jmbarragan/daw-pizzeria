<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas que el framework usa para guardar las sesiones en la base de datos y
 * los enlaces de recuperación de contraseña del personal. Se quedan con sus
 * nombres en inglés; la tabla users del esqueleto no se crea porque el
 * personal va en usuarios y los clientes en clientes.
 */
return new class extends Migration
{
    /**
     * Crea password_reset_tokens (un enlace por correo) y sessions, con
     * índices por usuario y por última actividad para limpiar las caducadas.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede crear alguna de las dos tablas
     */
    public function up(): void
    {
        Schema::create('password_reset_tokens', function (Blueprint $tabla) {
            $tabla->string('email')->primary();
            $tabla->string('token');
            $tabla->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $tabla) {
            $tabla->string('id')->primary();
            $tabla->foreignId('user_id')->nullable()->index();
            $tabla->string('ip_address', 45)->nullable();
            $tabla->text('user_agent')->nullable();
            $tabla->longText('payload');
            $tabla->integer('last_activity')->index();
        });
    }

    /**
     * Borra sessions y password_reset_tokens.
     *
     * @return void
     *
     * @throws QueryException si alguna de las dos tablas no se puede borrar
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
    }
};
