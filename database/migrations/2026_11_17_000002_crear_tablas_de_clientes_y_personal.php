<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clientes y personal. En clientes conviven invitados (sin contraseña) y
 * registrados; el teléfono y el correo se vacían al anonimizar. En usuarios
 * el rol distingue administrador y empleado, y activo permite dar de baja
 * sin perder quién decidió cada pedido.
 */
return new class extends Migration
{
    /**
     * Crea clientes, con índices para buscar por nombre y por teléfono
     * (RF-18) y el correo único, y usuarios, con el correo de acceso único.
     *
     * @return void
     *
     * @throws QueryException si MySQL rechaza la definición de clientes o de usuarios
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $tabla) {
            $tabla->comment('Clientes invitados y registrados');
            $tabla->increments('id')->comment('Id del cliente');
            $tabla->string('nombre', 80)->comment('Nombre');
            $tabla->string('telefono', 16)->nullable()->comment('Teléfono (+34...); NULL si se anonimizó');
            $tabla->string('email', 254)->nullable()->comment('Correo; puede faltar');
            $tabla->string('contrasena', 255)->nullable()->comment('Hash bcrypt (NULL = invitado)');
            $tabla->dateTime('email_verificado_en')->nullable()->comment('Correo verificado');
            $tabla->dateTime('creado_en')->useCurrent()->comment('Fecha de alta');
            $tabla->dateTime('anonimizado_en')->nullable()->comment('Fecha de anonimización');
            $tabla->unique('email', 'uq_clientes_email');
            $tabla->index('telefono', 'ix_clientes_telefono');
            $tabla->index('nombre', 'ix_clientes_nombre');
        });

        Schema::create('usuarios', function (Blueprint $tabla) {
            $tabla->comment('Personal de la pizzería');
            $tabla->smallIncrements('id')->comment('Id del empleado');
            $tabla->string('nombre', 80)->comment('Nombre y apellidos');
            $tabla->string('email', 254)->comment('Email de acceso');
            $tabla->string('contrasena', 255)->comment('Hash bcrypt');
            $tabla->enum('rol', ['administrador', 'empleado'])->default('empleado')->comment('Rol en la gestión');
            $tabla->boolean('activo')->default(true)->comment('FALSE = dado de baja');
            $tabla->dateTime('creado_en')->useCurrent()->comment('Fecha de alta');
            $tabla->unique('email', 'uq_usuarios_email');
        });
    }

    /**
     * Borra usuarios y clientes. Antes tienen que haberse deshecho las
     * migraciones que apuntan a ellas.
     *
     * @return void
     *
     * @throws QueryException si quedan pedidos, ajustes o bloqueos que apunten a ellas
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios');
        Schema::dropIfExists('clientes');
    }
};
