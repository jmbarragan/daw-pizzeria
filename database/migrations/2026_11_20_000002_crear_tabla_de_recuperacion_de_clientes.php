<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlaces para que un cliente con cuenta elija otra contraseña (RF-08). Van en
 * una tabla propia y no en password_reset_tokens, la del personal, porque una
 * misma dirección puede ser de un empleado y de un cliente. La
 * usa la zona pública con focaccia_web.
 */
return new class extends Migration
{
    /**
     * Crea recuperacion_clientes, con un enlace por correo.
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede crear la tabla
     */
    public function up(): void
    {
        Schema::create('recuperacion_clientes', function (Blueprint $tabla) {
            $tabla->comment('Enlaces de recuperación de la contraseña de los clientes');
            $tabla->string('email', 254)->primary()->comment('Correo de la cuenta');
            $tabla->string('token')->comment('Hash del token del enlace');
            $tabla->timestamp('created_at')->nullable()->comment('Momento en que se pidió');
        });
    }

    /**
     * Borra recuperacion_clientes.
     *
     * @return void
     *
     * @throws QueryException si la tabla no se puede borrar
     */
    public function down(): void
    {
        Schema::dropIfExists('recuperacion_clientes');
    }
};
