<?php

/**
 * Cálculo de los tres informes por intervalo de fechas (RF-20, CU-17).
 */

namespace App\Servicios;

use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Lee con la conexión «informes», la de focaccia_informes, que solo puede
 * consultar. Las ventas cobradas y los productos más vendidos
 * cuentan los pedidos recogidos por el día de su pago; los pedidos por estado,
 * todos los que tienen su fecha prevista en el intervalo. Los importes salen
 * de v_pedidos_totales y de las líneas, con el precio del momento del pedido.
 * La misma consulta sirve para la pantalla y para el PDF.
 */
class Informes
{
    /**
     * Informes que hay, con el título que se ve en la pantalla.
     *
     * @var array<string, string>
     */
    const TIPOS = [
        'ventas' => 'Ventas cobradas',
        'productos' => 'Productos más vendidos',
        'estados' => 'Pedidos por estado',
    ];

    /**
     * Calcula el informe pedido entre dos días, los dos incluidos.
     *
     * @param  string  $tipo  una de las claves de TIPOS
     * @param  CarbonInterface  $desde  primer día
     * @param  CarbonInterface  $hasta  último día
     * @return array{filas: list<array<string, mixed>>, total: array<string, mixed>}
     *
     * @throws InvalidArgumentException si el tipo no es uno de TIPOS; el controlador ya lo valida
     */
    public function calcular(string $tipo, CarbonInterface $desde, CarbonInterface $hasta): array
    {
        $filas = match ($tipo) {
            'ventas' => $this->ventas($desde, $hasta),
            'productos' => $this->productos($desde, $hasta),
            'estados' => $this->estados($desde, $hasta),
            default => throw new InvalidArgumentException("Informe desconocido: {$tipo}"),
        };

        return ['filas' => $filas, 'total' => $this->sumar($filas)];
    }

    /**
     * Pedidos cobrados e importe de cada día con algún pago.
     *
     * @param  CarbonInterface  $desde  primer día
     * @param  CarbonInterface  $hasta  último día
     * @return list<array{dia: string, pedidos: int, importe: float}>
     */
    private function ventas(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return $this->recogidos($desde, $hasta)
            ->join('v_pedidos_totales as totales', 'totales.pedido_id', '=', 'pedidos.id')
            ->selectRaw('DATE(pedidos.pagado_en) AS dia, COUNT(*) AS pedidos, SUM(totales.total) AS importe')
            ->groupByRaw('DATE(pedidos.pagado_en)')
            ->orderBy('dia')
            ->get()
            ->map(fn ($fila) => ['dia' => $fila->dia, 'pedidos' => (int) $fila->pedidos, 'importe' => (float) $fila->importe])
            ->all();
    }

    /**
     * Unidades e importe de cada producto en los pedidos cobrados, del más vendido al menos.
     *
     * @param  CarbonInterface  $desde  primer día
     * @param  CarbonInterface  $hasta  último día
     * @return list<array{producto: string, unidades: int, importe: float}>
     */
    private function productos(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return $this->recogidos($desde, $hasta)
            ->join('lineas_pedido as lineas', 'lineas.pedido_id', '=', 'pedidos.id')
            ->selectRaw('lineas.nombre_producto AS producto, SUM(lineas.cantidad) AS unidades,'
                .' SUM(lineas.cantidad * lineas.precio_unitario) AS importe')
            ->groupBy('lineas.nombre_producto')
            ->orderByDesc('unidades')->orderByDesc('importe')->orderBy('producto')
            ->get()
            ->map(fn ($fila) => ['producto' => $fila->producto, 'unidades' => (int) $fila->unidades, 'importe' => (float) $fila->importe])
            ->all();
    }

    /**
     * Número de pedidos e importe de cada estado, en el orden de la columna.
     *
     * @param  CarbonInterface  $desde  primer día
     * @param  CarbonInterface  $hasta  último día
     * @return list<array{estado: string, pedidos: int, importe: float}>
     */
    private function estados(CarbonInterface $desde, CarbonInterface $hasta): array
    {
        return $this->conexion()->table('pedidos')
            ->join('v_pedidos_totales as totales', 'totales.pedido_id', '=', 'pedidos.id')
            ->whereBetween('pedidos.fecha_prevista', [$desde->toDateString(), $hasta->toDateString()])
            ->selectRaw('pedidos.estado AS estado, COUNT(*) AS pedidos, SUM(totales.total) AS importe')
            ->groupBy('pedidos.estado')
            ->orderByRaw('MIN(pedidos.estado + 0)')
            ->get()
            ->map(fn ($fila) => ['estado' => $fila->estado, 'pedidos' => (int) $fila->pedidos, 'importe' => (float) $fila->importe])
            ->all();
    }

    /**
     * Pedidos recogidos cuyo pago cae entre el primer día a las 00:00 y el último a las 23:59:59.
     *
     * @param  CarbonInterface  $desde  primer día
     * @param  CarbonInterface  $hasta  último día
     * @return Builder
     */
    private function recogidos(CarbonInterface $desde, CarbonInterface $hasta): Builder
    {
        return $this->conexion()->table('pedidos')
            ->where('pedidos.estado', 'recogido')
            ->whereBetween('pedidos.pagado_en', [$desde->copy()->startOfDay(), $hasta->copy()->endOfDay()]);
    }

    /**
     * Suma las columnas numéricas de las filas.
     *
     * @param  list<array<string, mixed>>  $filas  filas del informe
     * @return array<string, int|float>
     */
    private function sumar(array $filas): array
    {
        $total = [];
        foreach ($filas as $fila) {
            foreach ($fila as $columna => $valor) {
                if (is_int($valor) || is_float($valor)) {
                    $total[$columna] = ($total[$columna] ?? 0) + $valor;
                }
            }
        }

        return $total;
    }

    /**
     * Conexión de solo lectura de los informes.
     *
     * @return Connection
     */
    private function conexion(): Connection
    {
        return DB::connection('informes');
    }
}
