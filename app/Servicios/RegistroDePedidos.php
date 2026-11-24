<?php

/**
 * Guarda un pedido, de la web o por teléfono, con su cliente y sus líneas (RF-04 y RF-14).
 */

namespace App\Servicios;

use App\Models\Cliente;
use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Usuario;
use App\Notifications\PedidoAceptado;
use App\Notifications\PedidoRecibido;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Todo va en una transacción: el cliente (uno nuevo por pedido, decisión
 * 028), el pedido con su código y su token (pendiente si llega por la web,
 * aceptado si llega por teléfono) y las líneas con el nombre y el precio del
 * momento. Si el código sorteado ya existe, se sortea
 * otro; MySQL deshace solo esa sentencia, así que la transacción sigue. Al
 * confirmarla se encola el correo para el cliente.
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
     * @param  array{nombre: string, telefono: string, email: string|null}  $cliente  datos del cliente
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas ya comprobadas
     * @param  string|null  $observaciones  notas del cliente
     * @param  CarbonInterface  $fechaPrevista  día de recogida previsto
     * @param  array<string, mixed>  $estado  columnas del estado; por defecto, pendiente por la web
     * @param  Cliente|null  $cuenta  cliente con la sesión iniciada; el pedido es suyo y no de un invitado nuevo
     * @return Pedido
     *
     * @throws UniqueConstraintViolationException si el código se repite en todos los intentos
     */
    public function registrar(array $cliente, Collection $lineas, ?string $observaciones, CarbonInterface $fechaPrevista,
        array $estado = ['canal' => 'web', 'estado' => 'pendiente'], ?Cliente $cuenta = null): Pedido
    {
        $pedido = DB::transaction(function () use ($cliente, $lineas, $observaciones, $fechaPrevista, $estado, $cuenta) {
            if ($cuenta) {
                // el nombre y el teléfono pueden cambiar de un pedido a otro; el correo es el de la cuenta
                $cuenta->update(['nombre' => $cliente['nombre'], 'telefono' => $cliente['telefono']]);
            }
            $titular = $cuenta ?? Cliente::create($cliente);
            $pedido = $this->insertarPedido($titular, $observaciones, $fechaPrevista, $estado);

            DB::table('lineas_pedido')->insert($lineas->map(fn (array $linea) => [
                'pedido_id' => $pedido->id,
                'producto_id' => $linea['producto']->id,
                'nombre_producto' => $linea['producto']->nombre,
                'precio_unitario' => $linea['producto']->precio,
                'cantidad' => $linea['unidades'],
            ])->all());

            return $pedido;
        });

        // el de la web avisa de que se ha recibido; el telefónico ya va aceptado
        $aviso = $pedido->estado === 'aceptado' ? new PedidoAceptado($pedido) : new PedidoRecibido($pedido);
        $aviso->enviar();

        return $pedido;
    }

    /**
     * Guarda un pedido que llega por teléfono (RF-14): nace aceptado, con su
     * hora de recogida y la persona que lo atiende como quien lo decide.
     *
     * @param  array{nombre: string, telefono: string, email: string|null}  $cliente  datos del cliente; el correo puede faltar
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas ya comprobadas
     * @param  string|null  $observaciones  notas del cliente
     * @param  CarbonInterface  $fechaPrevista  día de recogida
     * @param  Usuario  $quien  persona del personal que lo apunta
     * @param  string  $hora  hora de recogida HH:MM, una de las franjas del día
     * @param  Horario  $turno  turno al que pertenece esa hora
     * @return Pedido
     *
     * @throws UniqueConstraintViolationException si el código se repite en todos los intentos
     */
    public function registrarTelefonico(array $cliente, Collection $lineas, ?string $observaciones, CarbonInterface $fechaPrevista,
        Usuario $quien, string $hora, Horario $turno): Pedido
    {
        return $this->registrar($cliente, $lineas, $observaciones, $fechaPrevista, [
            'canal' => 'telefono',
            'estado' => 'aceptado',
            'usuario_id' => $quien->id,
            'decidido_en' => now(),
            'horario_id' => $turno->id,
            'hora_recogida' => $fechaPrevista->toDateString().' '.$hora.':00',
        ]);
    }

    /**
     * Inserta el pedido con su estado, sorteando otro código si ya existe.
     *
     * @param  Cliente  $cliente  cliente del pedido
     * @param  string|null  $observaciones  notas del cliente
     * @param  CarbonInterface  $fechaPrevista  día de recogida previsto
     * @param  array<string, mixed>  $estado  columnas del estado
     * @return Pedido
     *
     * @throws UniqueConstraintViolationException si el código se repite en todos los intentos
     */
    private function insertarPedido(Cliente $cliente, ?string $observaciones, CarbonInterface $fechaPrevista, array $estado): Pedido
    {
        // sin fracciones de segundo, igual que la columna, para que el token se pueda recalcular
        $creadoEn = now()->startOfSecond();

        for ($intento = 1; ; $intento++) {
            $codigo = $this->codigos->nuevo($fechaPrevista);

            try {
                return Pedido::forceCreate($estado + [
                    'codigo' => $codigo,
                    'cliente_id' => $cliente->id,
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
