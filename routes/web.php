<?php

/**
 * Rutas web de la aplicación: el inicio público y la zona de gestión. La
 * carta y el pedido se añaden con sus pantallas.
 */

use App\Http\Controllers\Gestion\AccesoController;
use App\Http\Controllers\Gestion\ContrasenaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');

// gestión: siempre con el usuario de MySQL focaccia_gestion
Route::prefix('gestion')->name('gestion.')->middleware('conexion.gestion')->group(function () {
    // una IP bloqueada no ve los formularios; cada envío cuenta para el límite por minuto
    Route::middleware(['guest:personal', 'ip.bloqueada'])->group(function () {
        Route::get('acceso', [AccesoController::class, 'mostrar'])->name('acceso');
        Route::get('contrasena', [ContrasenaController::class, 'olvidada'])->name('contrasena.olvidada');
        Route::get('contrasena/{token}', [ContrasenaController::class, 'nueva'])->name('contrasena.nueva');

        Route::middleware('throttle:acceso')->group(function () {
            Route::post('acceso', [AccesoController::class, 'entrar'])->name('entrar');
            Route::post('contrasena', [ContrasenaController::class, 'enviarEnlace'])->name('contrasena.enlace');
            Route::post('contrasena/nueva', [ContrasenaController::class, 'guardar'])->name('contrasena.guardar');
        });
    });

    Route::middleware(['auth:personal', 'cuenta.activa'])->group(function () {
        Route::post('salir', [AccesoController::class, 'salir'])->name('salir');
        Route::view('pedidos', 'gestion.pedidos')->name('pedidos');

        Route::middleware('rol:administrador')->group(function () {
            Route::view('usuarios', 'gestion.usuarios')->name('usuarios');
        });
    });
});
