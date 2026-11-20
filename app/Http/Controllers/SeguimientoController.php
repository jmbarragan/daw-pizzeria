<?php

/**
 * Página de seguimiento de un pedido a la que lleva el enlace (RF-05).
 */

namespace App\Http\Controllers;

use App\Servicios\EnlaceDeSeguimiento;
use Illuminate\View\View;

/**
 * Sin cuenta: quien tiene el enlace ve su pedido. Un token alterado, inventado
 * o de otra clave responde con la página 404 de siempre, sin decir si el
 * pedido existe ni dar ningún dato. El detalle y la cancelación llegan con la
 * gestión de pedidos.
 */
class SeguimientoController extends Controller
{
    /**
     * Estado del pedido del enlace.
     *
     * @param  string  $token  token de la dirección
     * @param  EnlaceDeSeguimiento  $enlaces  verificación del token
     * @return View
     */
    public function mostrar(string $token, EnlaceDeSeguimiento $enlaces): View
    {
        $pedido = $enlaces->pedidoDe($token);
        abort_if($pedido === null, 404);

        return view('seguimiento', ['pedido' => $pedido]);
    }
}
