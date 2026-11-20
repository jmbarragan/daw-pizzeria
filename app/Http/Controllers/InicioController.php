<?php

/**
 * Página de inicio con el estado del local y el horario (RF-02, pantalla P-01).
 */

namespace App\Http\Controllers;

use App\Servicios\AjustesPublicos;
use App\Servicios\Calendario;
use Illuminate\View\View;

/**
 * Dice si ahora está abierto (y hasta cuándo) o cerrado (y cuándo abre), con
 * el aviso de que fuera de horario se puede pedir igualmente y se revisará al
 * abrir. Debajo, los turnos de los próximos siete días.
 */
class InicioController extends Controller
{
    /**
     * Inicio de la zona pública.
     *
     * @param  Calendario  $calendario  turnos de apertura
     * @param  AjustesPublicos  $ajustes  teléfono del local, para cuando no hay aperturas
     * @return View
     */
    public function mostrar(Calendario $calendario, AjustesPublicos $ajustes): View
    {
        return view('inicio', [
            'abierto' => $calendario->turnoAbierto(),
            'siguiente' => $calendario->siguienteApertura(),
            'dias' => $calendario->proximosDias(),
            'telefono' => $ajustes->valor('telefono_local'),
        ]);
    }
}
