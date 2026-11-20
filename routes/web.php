<?php

/**
 * Rutas web de la aplicación: la zona pública (inicio, carta y cesta), con la
 * conexión «web», y la zona de gestión, con la conexión «gestion».
 */

use App\Http\Controllers\CartaController;
use App\Http\Controllers\CestaController;
use App\Http\Controllers\Gestion\AccesoController;
use App\Http\Controllers\Gestion\AlergenoController;
use App\Http\Controllers\Gestion\CategoriaController;
use App\Http\Controllers\Gestion\ContrasenaController;
use App\Http\Controllers\Gestion\HorarioController;
use App\Http\Controllers\Gestion\ProductoController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\SeguimientoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InicioController::class, 'mostrar'])->name('inicio');
Route::get('carta', [CartaController::class, 'mostrar'])->name('carta');
Route::view('privacidad', 'privacidad')->name('privacidad');

Route::controller(PedidoController::class)->prefix('pedido')->name('pedido')->group(function () {
    Route::get('/', 'formulario')->name('');
    Route::post('/', 'confirmar')->name('.confirmar');
    Route::get('recibido', 'recibido')->name('.recibido');
});

Route::get('seguimiento/{token}', [SeguimientoController::class, 'mostrar'])->name('seguimiento');
Route::controller(CestaController::class)->prefix('cesta')->name('cesta')->group(function () {
    Route::get('/', 'mostrar')->name('');
    Route::post('/', 'anadir')->name('.anadir');
    Route::patch('{producto}', 'cambiar')->name('.cambiar');
    Route::delete('{producto}', 'quitar')->name('.quitar');
});

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

        // horario de apertura por fechas (RF-19)
        Route::prefix('horario')->name('horario')->controller(HorarioController::class)->group(function () {
            Route::get('/', 'calendario')->name('');
            Route::get('turnos/nuevo', 'nuevo')->name('.turnos.nuevo');
            Route::post('turnos', 'guardar')->name('.turnos.guardar');
            Route::get('turnos/{turno}/editar', 'editar')->name('.turnos.editar');
            Route::put('turnos/{turno}', 'actualizar')->name('.turnos.actualizar');
            Route::delete('turnos/{turno}', 'borrar')->name('.turnos.borrar');
        });

        // carta: productos, categorías y alérgenos (RF-15 a RF-17)
        Route::prefix('carta')->name('carta')->group(function () {
            Route::get('/', [ProductoController::class, 'listado'])->name('');

            Route::prefix('productos')->name('.productos')->controller(ProductoController::class)->group(function () {
                Route::get('nuevo', 'nuevo')->name('.nuevo');
                Route::post('/', 'guardar')->name('.guardar');
                Route::get('{producto}', 'editar')->name('.editar');
                Route::put('{producto}', 'actualizar')->name('.actualizar');
                Route::delete('{producto}', 'borrar')->name('.borrar');
                Route::patch('{producto}/volver', 'volver')->name('.volver');
            });

            Route::prefix('categorias')->name('.categorias')->controller(CategoriaController::class)->group(function () {
                Route::get('/', 'listado')->name('');
                Route::get('nueva', 'nueva')->name('.nueva');
                Route::post('/', 'guardar')->name('.guardar');
                Route::get('{categoria}/editar', 'editar')->name('.editar');
                Route::put('{categoria}', 'actualizar')->name('.actualizar');
                Route::delete('{categoria}', 'borrar')->name('.borrar');
            });

            Route::prefix('alergenos')->name('.alergenos')->controller(AlergenoController::class)->group(function () {
                Route::get('/', 'listado')->name('');
                Route::get('nuevo', 'nuevo')->name('.nuevo');
                Route::post('/', 'guardar')->name('.guardar');
                Route::get('{alergeno}/editar', 'editar')->name('.editar');
                Route::put('{alergeno}', 'actualizar')->name('.actualizar');
                Route::delete('{alergeno}', 'borrar')->name('.borrar');
            });
        });

        Route::middleware('rol:administrador')->group(function () {
            Route::view('usuarios', 'gestion.usuarios')->name('usuarios');
        });
    });
});
