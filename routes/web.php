<?php

/**
 * Rutas web de la aplicación: la zona pública (inicio, carta y cesta), con la
 * conexión «web», y la zona de gestión, con la conexión «gestion».
 */

use App\Http\Controllers\CartaController;
use App\Http\Controllers\CestaController;
use App\Http\Controllers\ContrasenaDeClienteController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\Gestion\AccesoController;
use App\Http\Controllers\Gestion\AjusteController;
use App\Http\Controllers\Gestion\AlergenoController;
use App\Http\Controllers\Gestion\CategoriaController;
use App\Http\Controllers\Gestion\ClienteController;
use App\Http\Controllers\Gestion\ContrasenaController;
use App\Http\Controllers\Gestion\HorarioController;
use App\Http\Controllers\Gestion\InformeController;
use App\Http\Controllers\Gestion\PedidoController as GestionDePedidos;
use App\Http\Controllers\Gestion\ProductoController;
use App\Http\Controllers\Gestion\UsuarioController;
use App\Http\Controllers\InicioController;
use App\Http\Controllers\MisPedidosController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\SeguimientoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InicioController::class, 'mostrar'])->name('inicio');
Route::get('carta', [CartaController::class, 'mostrar'])->name('carta');
Route::view('privacidad', 'privacidad')->name('privacidad');
Route::view('ayuda', 'ayuda')->name('ayuda');

Route::controller(PedidoController::class)->prefix('pedido')->name('pedido')->group(function () {
    Route::get('/', 'formulario')->name('');
    Route::post('/', 'confirmar')->middleware('throttle:pedidos')->name('.confirmar');
    Route::get('recibido', 'recibido')->name('.recibido');
});

Route::controller(SeguimientoController::class)->prefix('seguimiento/{token}')->name('seguimiento')->group(function () {
    Route::get('/', 'mostrar')->name('');
    Route::get('cancelar', 'confirmarCancelacion')->name('.cancelacion');
    Route::post('cancelar', 'cancelar')->name('.cancelar');
});

// cuenta del cliente: registro tras el pedido, verificación, acceso e historial (RF-08)
Route::post('pedido/recibido/cuenta', [CuentaController::class, 'registrar'])->name('cuenta.registrar');
Route::get('cuenta/verificar/{cliente}/{hash}', [CuentaController::class, 'verificar'])
    ->middleware('signed:relative')->name('cuenta.verificar');

Route::middleware(['guest:cliente', 'ip.bloqueada'])->group(function () {
    Route::get('acceder', [CuentaController::class, 'mostrarAcceso'])->name('acceder');
    Route::post('acceder', [CuentaController::class, 'entrar'])->middleware('throttle:acceso')->name('entrar');

    Route::controller(ContrasenaDeClienteController::class)->prefix('contrasena')->name('contrasena')->group(function () {
        Route::get('/', 'olvidada')->name('.olvidada');
        Route::post('/', 'enviarEnlace')->middleware('throttle:acceso')->name('.enlace');
        Route::get('{token}', 'nueva')->name('.nueva');
        Route::post('nueva', 'guardar')->middleware('throttle:acceso')->name('.guardar');
    });
});

Route::middleware(['auth:cliente', 'cuenta.cliente'])->group(function () {
    Route::get('mis-pedidos', [MisPedidosController::class, 'listado'])->name('mis-pedidos');
    Route::post('mis-pedidos/{pedido:codigo}/repetir', [MisPedidosController::class, 'repetir'])->name('mis-pedidos.repetir');
    Route::post('salir', [CuentaController::class, 'salir'])->name('salir');
});

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
        // ayuda de la gestión por perfiles, para empleado y administrador (RF-09)
        Route::view('ayuda', 'gestion.ayuda')->name('ayuda');
        Route::prefix('pedidos')->name('pedidos')->controller(GestionDePedidos::class)->group(function () {
            Route::get('/', 'panel')->name('');
            Route::get('telefonico', 'telefonico')->name('.telefonico');
            Route::post('telefonico', 'guardarTelefonico')->name('.telefonico.guardar');
            Route::get('{pedido:codigo}', 'detalle')->name('.detalle');
            Route::post('{pedido:codigo}/aceptar', 'aceptar')->name('.aceptar');
            Route::post('{pedido:codigo}/rechazar', 'rechazar')->name('.rechazar');
            Route::post('{pedido:codigo}/estado', 'avanzar')->name('.avanzar');
        });

        // clientes, con anonimización de los que tienen pedidos (RF-18)
        Route::prefix('clientes')->name('clientes')->controller(ClienteController::class)->group(function () {
            Route::get('/', 'listado')->name('');
            Route::get('nuevo', 'nuevo')->name('.nuevo');
            Route::post('/', 'guardar')->name('.guardar');
            Route::get('{cliente}/editar', 'editar')->name('.editar');
            Route::put('{cliente}', 'actualizar')->name('.actualizar');
            Route::delete('{cliente}', 'borrar')->name('.borrar');
        });

        // informes por intervalo de fechas, con la conexión de solo lectura (RF-20)
        Route::get('informes', [InformeController::class, 'mostrar'])->name('informes');
        Route::get('informes/pdf', [InformeController::class, 'pdf'])->name('informes.pdf');

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
            // usuarios del personal, con al menos un administrador activo (RF-21)
            Route::prefix('usuarios')->name('usuarios')->controller(UsuarioController::class)->group(function () {
                Route::get('/', 'listado')->name('');
                Route::get('nuevo', 'nuevo')->name('.nuevo');
                Route::post('/', 'guardar')->name('.guardar');
                Route::get('{usuario}/editar', 'editar')->name('.editar');
                Route::put('{usuario}', 'actualizar')->name('.actualizar');
                Route::patch('{usuario}/desactivar', 'desactivar')->name('.desactivar');
                Route::patch('{usuario}/activar', 'activar')->name('.activar');
            });

            // ajustes del local, servidor de correo y bloqueos de IP (RF-21)
            Route::prefix('ajustes')->name('ajustes')->controller(AjusteController::class)->group(function () {
                Route::get('/', 'mostrar')->name('');
                Route::put('/', 'guardar')->name('.guardar');
                Route::patch('bloqueos/{bloqueo}/desbloquear', 'desbloquear')->name('.desbloquear');
            });
        });
    });
});
