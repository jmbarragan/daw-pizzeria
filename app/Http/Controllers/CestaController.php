<?php

/**
 * Cesta de la compra de la zona pública (RF-03, pantalla P-03).
 */

namespace App\Http\Controllers;

use App\Models\Producto;
use App\Servicios\AjustesPublicos;
use App\Servicios\Calendario;
use App\Servicios\Cesta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Ver la cesta, añadir desde la carta, cambiar unidades y quitar líneas, sin
 * cuenta obligatoria. Cada cambio se comprueba en el servidor: el producto se
 * puede pedir, de 1 a 20 unidades y sin pasar del máximo de pizzas. Si se
 * pasaría, se avisa con la ventana que pide llamar al local.
 */
class CestaController extends Controller
{
    /**
     * Prepara el controlador con la cesta de la sesión.
     *
     * @param  Cesta  $cesta  reglas y contenido de la cesta
     */
    public function __construct(private readonly Cesta $cesta) {}

    /**
     * Página de la cesta con sus líneas, el total y si se puede tramitar.
     *
     * @param  Calendario  $calendario  para el aviso de abierto o cerrado y la fecha prevista
     * @param  AjustesPublicos  $ajustes  teléfono del local
     * @return View
     */
    public function mostrar(Calendario $calendario, AjustesPublicos $ajustes): View
    {
        $lineas = $this->cesta->lineas();
        $agotados = $lineas->filter(fn (array $linea) => ! $linea['producto']->disponible);
        $hayFecha = $calendario->fechaPrevista() !== null;

        return view('cesta', [
            'lineas' => $lineas,
            'total' => $this->cesta->total($lineas),
            'agotados' => $agotados,
            'abierto' => $calendario->turnoAbierto(),
            'siguiente' => $calendario->siguienteApertura(),
            'telefono' => $ajustes->valor('telefono_local'),
            'sePuedeTramitar' => $lineas->isNotEmpty() && $agotados->isEmpty() && $hayFecha,
        ]);
    }

    /**
     * Añade una unidad del producto y vuelve a su sitio en la carta.
     *
     * @param  Request  $peticion  petición con «producto»
     * @return RedirectResponse
     */
    public function anadir(Request $peticion): RedirectResponse
    {
        $producto = Producto::enCarta()
            ->whereKey((int) $peticion->input('producto'))
            ->whereHas('categoria', fn ($categoria) => $categoria->where('activa', true))
            ->first();

        if (! $producto) {
            return redirect()->route('carta')->with('error', 'Ese producto ya no está en la carta.');
        }

        $volver = redirect()->to(route('carta').'#producto-'.$producto->id);
        if (! $producto->disponible) {
            return $volver->with('error', "Lo sentimos: «{$producto->nombre}» se ha agotado.");
        }

        $unidades = ($this->cesta->unidades()[$producto->id] ?? 0) + 1;

        if ($aviso = $this->porQueNo($producto, $unidades)) {
            return $volver->with($aviso);
        }

        $this->cesta->poner($producto, $unidades);

        return $volver->with('estado', "Se ha añadido «{$producto->nombre}» a la cesta ({$unidades} en total).");
    }

    /**
     * Cambia las unidades de una línea de la cesta.
     *
     * @param  Request  $peticion  petición con «unidades»
     * @param  Producto  $producto  producto de la línea
     * @return RedirectResponse
     */
    public function cambiar(Request $peticion, Producto $producto): RedirectResponse
    {
        $peticion->validate(
            ['unidades' => ['required', 'integer', 'between:1,'.Cesta::MAXIMO_POR_PRODUCTO]],
            ['unidades.between' => 'Cada producto va de 1 a '.Cesta::MAXIMO_POR_PRODUCTO.' unidades; para no pedirlo, usa «Quitar».']
        );

        if (! $this->estaEnLaCesta($producto)) {
            return $this->noEstaEnLaCesta();
        }

        if ($aviso = $this->porQueNo($producto, (int) $peticion->input('unidades'))) {
            return redirect()->route('cesta')->with($aviso);
        }

        $this->cesta->poner($producto, (int) $peticion->input('unidades'));

        return redirect()->route('cesta');
    }

    /**
     * Quita una línea de la cesta.
     *
     * @param  Producto  $producto  producto de la línea
     * @return RedirectResponse
     */
    public function quitar(Producto $producto): RedirectResponse
    {
        if (! $this->estaEnLaCesta($producto)) {
            return $this->noEstaEnLaCesta();
        }

        $this->cesta->quitar($producto);

        return redirect()->route('cesta')->with('estado', "Se ha quitado «{$producto->nombre}» de la cesta.");
    }

    /**
     * Indica si el producto está en la cesta de esta sesión. Cambiar y quitar
     * solo dicen el nombre de lo que ya está en ella: si no, cualquiera podría
     * probar números y sacar los nombres de productos retirados u ocultos.
     *
     * @param  Producto  $producto  producto de la dirección
     * @return bool
     */
    private function estaEnLaCesta(Producto $producto): bool
    {
        return isset($this->cesta->unidades()[$producto->id]);
    }

    /**
     * Respuesta, sin el nombre del producto, cuando no está en la cesta.
     *
     * @return RedirectResponse
     */
    private function noEstaEnLaCesta(): RedirectResponse
    {
        return redirect()->route('cesta')->with('error', 'Ese producto no está en tu cesta.');
    }

    /**
     * Motivo por el que un producto no puede quedar con esas unidades, como
     * datos para el aviso, o null si puede.
     *
     * @param  Producto  $producto  producto que cambia
     * @param  int  $unidades  unidades que tendría
     * @return array<string, mixed>|null
     */
    private function porQueNo(Producto $producto, int $unidades): ?array
    {
        if (! $producto->disponible) {
            return ['error' => "Lo sentimos: «{$producto->nombre}» se ha agotado. Quítalo de la cesta para seguir."];
        }

        if ($unidades > Cesta::MAXIMO_POR_PRODUCTO) {
            return ['error' => 'Ya tienes '.Cesta::MAXIMO_POR_PRODUCTO." unidades de «{$producto->nombre}», que es lo máximo por producto."];
        }

        $maximo = $this->cesta->maximoDePizzas();
        if ($this->cesta->pizzasCon($producto, $unidades) > $maximo) {
            return ['error' => "Por la web se pueden pedir como mucho {$maximo} pizzas.", 'aviso_pizzas' => $maximo];
        }

        return null;
    }
}
