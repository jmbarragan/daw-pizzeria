<?php

/**
 * Cesta de la compra de la zona pública, guardada en la sesión (RF-03).
 */

namespace App\Servicios;

use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * En la sesión solo se guarda «id del producto => unidades»; los nombres y
 * los precios se leen siempre de la carta, así que la cesta enseña el precio
 * de ahora. Reúne las reglas que comparten la carta, la cesta y el envío del
 * pedido: de 1 a 20 unidades por producto y, como mucho, el número de pizzas
 * del ajuste maximo_pizzas, contando solo la categoría «Pizzas».
 */
class Cesta
{
    /**
     * Unidades máximas de un mismo producto (RF-03).
     *
     * @var int
     */
    const MAXIMO_POR_PRODUCTO = 20;

    /**
     * Categoría cuyas unidades cuentan para el límite de pizzas.
     *
     * @var string
     */
    const CATEGORIA_DE_PIZZAS = 'Pizzas';

    /**
     * Lee el máximo de pizzas de los ajustes públicos.
     *
     * @param  AjustesPublicos  $ajustes  ajustes que puede leer la zona pública
     */
    public function __construct(private readonly AjustesPublicos $ajustes) {}

    /**
     * Unidades de cada producto, tal como están en la sesión.
     *
     * @return array<int, int>
     */
    public function unidades(): array
    {
        return session('cesta', []);
    }

    /**
     * Productos de la cesta que siguen en la carta, con sus unidades y su
     * importe. Los que ya no se pueden ver (retirados o de una categoría
     * oculta) se sacan de la cesta, y el número de los que se han sacado se
     * guarda para avisar.
     *
     * @return Collection<int, array{producto: Producto, unidades: int, importe: float}>
     */
    public function lineas(): Collection
    {
        $unidades = $this->unidades();
        if ($unidades === []) {
            return collect();
        }

        $productos = Producto::enCarta()
            ->whereKey(array_keys($unidades))
            ->whereHas('categoria', fn ($categoria) => $categoria->where('activa', true))
            ->with(['categoria', 'alergenos'])
            ->orderBy('nombre')
            ->get();

        $quitados = count($unidades) - $productos->count();
        if ($quitados > 0) {
            session()->put('cesta', array_intersect_key($unidades, $productos->keyBy('id')->all()));
            session()->now('quitados_de_la_cesta', $quitados);
        }

        return $productos->map(fn (Producto $producto) => [
            'producto' => $producto,
            'unidades' => $unidades[$producto->id],
            'importe' => $unidades[$producto->id] * (float) $producto->precio,
        ]);
    }

    /**
     * Suma de los importes de unas líneas.
     *
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas de la cesta
     * @return float
     */
    public function total(Collection $lineas): float
    {
        return round($lineas->sum('importe'), 2);
    }

    /**
     * Pizzas que habría en la cesta con un producto puesto a unas unidades.
     *
     * @param  Producto|null  $producto  producto que cambia, o null para contar la cesta como está
     * @param  int  $unidadesNuevas  unidades que tendría ese producto
     * @return int
     */
    public function pizzasCon(?Producto $producto = null, int $unidadesNuevas = 0): int
    {
        $unidades = $this->unidades();
        if ($producto) {
            $unidades[$producto->id] = $unidadesNuevas;
        }

        $pizzas = Producto::whereKey(array_keys($unidades))
            ->whereHas('categoria', fn ($categoria) => $categoria->where('nombre', self::CATEGORIA_DE_PIZZAS))
            ->pluck('id');

        return $pizzas->sum(fn (int $id) => $unidades[$id] ?? 0);
    }

    /**
     * Máximo de pizzas por pedido, del ajuste maximo_pizzas (20 si falta).
     *
     * @return int
     */
    public function maximoDePizzas(): int
    {
        return (int) $this->ajustes->valor('maximo_pizzas', '20');
    }

    /**
     * Guarda las unidades de un producto.
     *
     * @param  Producto  $producto  producto de la cesta
     * @param  int  $unidades  de 1 a MAXIMO_POR_PRODUCTO
     * @return void
     */
    public function poner(Producto $producto, int $unidades): void
    {
        $cesta = $this->unidades();
        $cesta[$producto->id] = $unidades;
        session()->put('cesta', $cesta);
    }

    /**
     * Quita un producto de la cesta.
     *
     * @param  Producto  $producto  producto que se quita
     * @return void
     */
    public function quitar(Producto $producto): void
    {
        $cesta = $this->unidades();
        unset($cesta[$producto->id]);
        session()->put('cesta', $cesta);
    }
}
