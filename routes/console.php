<?php

/**
 * Tareas programadas. En el servidor, el cron lanza «php artisan schedule:run»
 * cada minuto y Laravel decide qué toca; en local, «php artisan schedule:work».
 */

use Illuminate\Support\Facades\Schedule;

// pedidos pendientes que ya no se pueden recoger
Schedule::command('pedidos:rechazar-pasados')->everyFifteenMinutes()->withoutOverlapping();
