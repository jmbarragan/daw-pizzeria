<?php

/**
 * Seguimiento y cancelación de un pedido desde su enlace (RF-06 y RF-07, P-06).
 */

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Servicios\AjustesPublicos;
use App\Servicios\CambiosDeEstado;
use App\Servicios\EnlaceDeSeguimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Sin cuenta: quien tiene el enlace ve su pedido y, mientras está pendiente,
 * puede cancelarlo. Un token alterado, inventado o de otra clave responde con
 * la página 404 de siempre, sin decir si el pedido existe ni dar ningún dato.
 */
class SeguimientoController extends Controller
{
    /**
     * Estado, hora y lugar de recogida, líneas y total del pedido del enlace.
     *
     * @param  string  $token  token de la dirección
     * @param  EnlaceDeSeguimiento  $enlaces  verificación del token
     * @param  AjustesPublicos  $ajustes  dirección y teléfono del local
     * @return View
     *
     * @throws HttpException 404 si el token no es válido, sin más datos
     */
    public function mostrar(string $token, EnlaceDeSeguimiento $enlaces, AjustesPublicos $ajustes): View
    {
        $pedido = $this->pedido($token, $enlaces);
        $pedido->load('lineas');

        return view('seguimiento', [
            'pedido' => $pedido,
            'total' => DB::table('v_pedidos_totales')->where('pedido_id', $pedido->id)->value('total'),
            'direccion' => $ajustes->valor('direccion_local'),
            'telefono' => $ajustes->valor('telefono_local'),
        ]);
    }

    /**
     * Pregunta antes de cancelar. Si el pedido ya no está pendiente, vuelve al
     * seguimiento, donde se ve su estado.
     *
     * @param  string  $token  token de la dirección
     * @param  EnlaceDeSeguimiento  $enlaces  verificación del token
     * @return View|RedirectResponse
     *
     * @throws HttpException 404 si el token no es válido, sin más datos
     */
    public function confirmarCancelacion(string $token, EnlaceDeSeguimiento $enlaces): View|RedirectResponse
    {
        $pedido = $this->pedido($token, $enlaces);

        if ($pedido->estado !== 'pendiente') {
            return redirect()->route('seguimiento', $token);
        }

        return view('cancelar-pedido', ['pedido' => $pedido]);
    }

    /**
     * Cancela el pedido si sigue pendiente. Si el local lo ha decidido antes,
     * no se cancela y se dice cómo está y a qué teléfono llamar (CU-06, 4a).
     *
     * @param  string  $token  token de la dirección
     * @param  EnlaceDeSeguimiento  $enlaces  verificación del token
     * @param  CambiosDeEstado  $cambios  cambio condicionado a que siga pendiente
     * @param  AjustesPublicos  $ajustes  teléfono del local
     * @return RedirectResponse
     *
     * @throws HttpException 404 si el token no es válido, sin más datos
     */
    public function cancelar(string $token, EnlaceDeSeguimiento $enlaces, CambiosDeEstado $cambios, AjustesPublicos $ajustes): RedirectResponse
    {
        $pedido = $this->pedido($token, $enlaces);

        if ($cambios->cancelar($pedido)) {
            return redirect()->route('seguimiento', $token)->with('estado', "Pedido {$pedido->codigo} cancelado.");
        }

        $aviso = 'No se ha podido cancelar: el pedido ya está «'.mb_strtolower(Pedido::ETIQUETAS[$pedido->estado]).'».';
        $telefono = $ajustes->valor('telefono_local');
        if ($telefono && ! in_array($pedido->estado, Pedido::ESTADOS_FINALES, true)) {
            $aviso .= " Si necesitas cambiarlo, llámanos al {$telefono}.";
        }

        return redirect()->route('seguimiento', $token)->with('error', $aviso);
    }

    /**
     * Pedido al que lleva un enlace válido.
     *
     * @param  string  $token  token de la dirección
     * @param  EnlaceDeSeguimiento  $enlaces  verificación del token
     * @return Pedido pedido cuyo código y hora firman el token
     *
     * @throws HttpException 404 si el token no es válido, sin más datos
     */
    private function pedido(string $token, EnlaceDeSeguimiento $enlaces): Pedido
    {
        $pedido = $enlaces->pedidoDe($token);
        abort_if($pedido === null, 404);

        return $pedido;
    }
}
