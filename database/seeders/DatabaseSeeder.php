<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Punto de entrada de «php artisan db:seed --database=admin». Carga los datos
 * de prueba del diseño sobre una base recién migrada.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta los sembradores en el orden de las claves externas y dentro de
     * una transacción: si uno falla, no queda nada a medias.
     *
     * @return void
     *
     * @throws Throwable si algún sembrador falla; la transacción se deshace
     */
    public function run(): void
    {
        DB::transaction(fn () => $this->call([
            CartaSeeder::class,
            PersonalYClientesSeeder::class,
            HorarioYPedidosSeeder::class,
            AjustesYBloqueosSeeder::class,
        ]));
    }
}
