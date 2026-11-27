<?php

/**
 * Historial del cliente registrado y repetición de un pedido (RF-08, CU-09, pantalla P-08).
 */

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Models\Producto;
use App\Servicios\Cesta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * El historial empieza en el pedido con el que se registró el cliente: los
 * de invitado anteriores no se le asignan. Repetir copia las
 * líneas a la cesta con los precios de ahora; lo que ya no se puede pedir se
 * queda fuera y se dice qué.
 */
class MisPedidosController extends Controller
{
    /**
     * Pedidos del cliente, de los más nuevos a los más antiguos, con su total.
     *
     * @param  Request  $peticion  petición con el cliente de la sesión
     * @return View
     */
    public function listado(Request $peticion): View
    {
        $pedidos = Pedido::query()
            ->where('cliente_id', $peticion->user('cliente')->id)
            ->join('v_pedidos_totales as totales', 'totales.pedido_id', '=', 'pedidos.id')
            ->select('pedidos.*', 'totales.total as importe')
            ->orderByDesc('pedidos.creado_en')
            // dos pedidos del mismo segundo: primero el último que se guardó
            ->orderByDesc('pedidos.id')
            ->paginate(15);

        return view('mis-pedidos', ['pedidos' => $pedidos]);
    }

    /**
     * Pone en la cesta las líneas de un pedido propio que aún se pueden pedir.
     *
     * @param  Request  $peticion  petición con el cliente de la sesión
     * @param  Pedido  $pedido  pedido de la dirección, por su código
     * @param  Cesta  $cesta  cesta de la sesión
     * @return RedirectResponse
     *
     * @throws HttpException 404 si el pedido es de otro cliente
     */
    public function repetir(Request $peticion, Pedido $pedido, Cesta $cesta): RedirectResponse
    {
        // un pedido de otro cliente responde igual que uno que no existe
        abort_unless($pedido->cliente_id === $peticion->user('cliente')->id, 404);

        $fuera = [];
        foreach ($pedido->lineas as $linea) {
            $producto = Producto::enCarta()
                ->where('disponible', true)
                ->whereHas('categoria', fn ($categoria) => $categoria->where('activa', true))
                ->find($linea->producto_id);

            if ($producto) {
                $cesta->poner($producto, $linea->cantidad);
            } else {
                $fuera[] = "«{$linea->nombre_producto}»";
            }
        }

        $volver = redirect()->route('cesta');
        if ($fuera === []) {
            return $volver->with('estado', "Las líneas del pedido {$pedido->codigo} están en la cesta, con los precios de hoy.");
        }

        $aviso = count($fuera) === 1
            ? "Ya no se puede pedir {$fuera[0]}, así que se ha quedado fuera de la cesta."
            : 'Ya no se pueden pedir '.implode(', ', $fuera).', así que se han quedado fuera de la cesta.';

        return $volver->with(count($fuera) === $pedido->lineas->count() ? 'error' : 'estado', $aviso);
    }
}
