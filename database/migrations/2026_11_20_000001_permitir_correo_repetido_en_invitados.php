<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Correo único solo para los clientes con cuenta (decisión 031). Un invitado
 * es un cliente sin contraseña, y cada pedido crea el suyo (decisión 028), así
 * que el mismo correo puede repetirse entre invitados. El índice funcional
 * deja fuera a los invitados porque MySQL admite varios NULL en un índice
 * único.
 */
return new class extends Migration
{
    /**
     * Cambia uq_clientes_email por un índice sobre IF(contrasena IS NULL, NULL, email).
     *
     * @return void
     *
     * @throws QueryException si MySQL no puede cambiar el índice
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE clientes DROP INDEX uq_clientes_email');
        DB::statement('CREATE UNIQUE INDEX uq_clientes_email ON clientes ((IF(contrasena IS NULL, NULL, email)))');
    }

    /**
     * Vuelve al correo único para todos los clientes.
     *
     * @return void
     *
     * @throws QueryException si ya hay invitados que comparten correo
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE clientes DROP INDEX uq_clientes_email');
        DB::statement('CREATE UNIQUE INDEX uq_clientes_email ON clientes (email)');
    }
};
