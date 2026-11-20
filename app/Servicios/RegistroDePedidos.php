<?php

/**
 * Guarda un pedido de la web con su cliente y sus líneas (RF-04, CU-03).
 */

namespace App\Servicios;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Todo va en una transacción: el cliente (uno nuevo por pedido, decisión
 * 028), el pedido pendiente con su código y su token, y las líneas con el
 * nombre y el precio del momento. Si el código sorteado ya existe, se sortea
 * otro; MySQL deshace solo esa sentencia, así que la transacción sigue.
 */
class RegistroDePedidos
{
    /**
     * Veces que se sortea el código antes de darse por vencido. Con 16,7
     * millones de códigos por año, repetir cinco seguidos es casi imposible.
     *
     * @var int
     */
    private const INTENTOS = 5;

    /**
     * Prepara el registro con el generador de códigos y el de tokens.
     *
     * @param  CodigoDePedido  $codigos  sorteo del código
     * @param  EnlaceDeSeguimiento  $enlaces  token del enlace de seguimiento
     */
    public function __construct(
        private readonly CodigoDePedido $codigos,
        private readonly EnlaceDeSeguimiento $enlaces,
    ) {}

    /**
     * Guarda el cliente, el pedido y sus líneas, o nada si algo falla.
     *
     * @param  array{nombre: string, telefono: string, email: string}  $cliente  datos del invitado
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas ya comprobadas
     * @param  string|null  $observaciones  notas del cliente
     * @param  CarbonInterface  $fechaPrevista  día de recogida previsto
     * @return Pedido
     *
     * @throws UniqueConstraintViolationException si el código se repite en todos los intentos
     */
    public function registrar(array $cliente, Collection $lineas, ?string $observaciones, CarbonInterface $fechaPrevista): Pedido
    {
        return DB::transaction(function () use ($cliente, $lineas, $observaciones, $fechaPrevista) {
            $nuevo = Cliente::create($cliente);
            $pedido = $this->insertarPedido($nuevo, $observaciones, $fechaPrevista);

            DB::table('lineas_pedido')->insert($lineas->map(fn (array $linea) => [
                'pedido_id' => $pedido->id,
                'producto_id' => $linea['producto']->id,
                'nombre_producto' => $linea['producto']->nombre,
                'precio_unitario' => $linea['producto']->precio,
                'cantidad' => $linea['unidades'],
            ])->all());

            return $pedido;
        });
    }

    /**
     * Inserta el pedido pendiente, sorteando otro código si ya existe.
     *
     * @param  Cliente  $cliente  cliente del pedido
     * @param  string|null  $observaciones  notas del cliente
     * @param  CarbonInterface  $fechaPrevista  día de recogida previsto
     * @return Pedido
     *
     * @throws UniqueConstraintViolationException si el código se repite en todos los intentos
     */
    private function insertarPedido(Cliente $cliente, ?string $observaciones, CarbonInterface $fechaPrevista): Pedido
    {
        // sin fracciones de segundo, igual que la columna, para que el token se pueda recalcular
        $creadoEn = now()->startOfSecond();

        for ($intento = 1; ; $intento++) {
            $codigo = $this->codigos->nuevo($fechaPrevista);

            try {
                return Pedido::forceCreate([
                    'codigo' => $codigo,
                    'cliente_id' => $cliente->id,
                    'canal' => 'web',
                    'estado' => 'pendiente',
                    'token_seguimiento' => $this->enlaces->calcular($codigo, $creadoEn),
                    'observaciones' => $observaciones,
                    'creado_en' => $creadoEn,
                    'fecha_prevista' => $fechaPrevista->toDateString(),
                ]);
            } catch (UniqueConstraintViolationException $repetido) {
                if ($intento >= self::INTENTOS || ! str_contains($repetido->getMessage(), 'codigo')) {
                    throw $repetido;
                }
            }
        }
    }
}
