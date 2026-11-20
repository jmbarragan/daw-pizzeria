<?php

/**
 * Cambios de estado de un pedido con control de concurrencia (RF-12 y RF-13).
 */

namespace App\Servicios;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Notifications\PedidoAceptado;
use App\Notifications\PedidoRechazado;
use Illuminate\Support\Facades\DB;

/**
 * Cada cambio es una sola sentencia UPDATE que solo se aplica si el pedido
 * sigue en el estado de partida. Si el cliente cancela y un empleado acepta a
 * la vez, MySQL aplica una de las dos y la otra no cambia ninguna fila: quien
 * llega tarde se entera por el valor devuelto y avisa con el estado nuevo.
 * Los campos que se rellenan son los que piden las restricciones CHECK de la
 * tabla para cada estado. Aceptar y rechazar avisan al cliente por correo,
 * también cuando rechaza la orden automática.
 */
class CambiosDeEstado
{
    /**
     * Acepta un pedido pendiente con su hora de recogida.
     *
     * @param  Pedido  $pedido  pedido que se acepta
     * @param  Usuario  $quien  persona del personal que lo decide
     * @param  string  $hora  hora de recogida HH:MM, una de las franjas del día previsto
     * @param  Horario  $turno  turno al que pertenece esa hora
     * @return bool false si el pedido ya no estaba pendiente
     */
    public function aceptar(Pedido $pedido, Usuario $quien, string $hora, Horario $turno): bool
    {
        $aceptado = $this->cambiar($pedido, 'pendiente', [
            'estado' => 'aceptado',
            'usuario_id' => $quien->id,
            'decidido_en' => now(),
            'horario_id' => $turno->id,
            'hora_recogida' => $pedido->fecha_prevista->toDateString().' '.$hora.':00',
        ]);

        if ($aceptado) {
            (new PedidoAceptado($pedido))->enviar();
        }

        return $aceptado;
    }

    /**
     * Rechaza un pedido pendiente con su motivo, que lo cierra.
     *
     * @param  Pedido  $pedido  pedido que se rechaza
     * @param  Usuario  $quien  persona del personal que lo decide
     * @param  string  $motivo  explicación que recibirá el cliente
     * @return bool false si el pedido ya no estaba pendiente
     */
    public function rechazar(Pedido $pedido, Usuario $quien, string $motivo): bool
    {
        $rechazado = $this->cambiar($pedido, 'pendiente', [
            'estado' => 'rechazado',
            'usuario_id' => $quien->id,
            'decidido_en' => now(),
            'motivo_rechazo' => $motivo,
            'cerrado_en' => now(),
        ]);

        if ($rechazado) {
            (new PedidoRechazado($pedido))->enviar();
        }

        return $rechazado;
    }

    /**
     * Cancela un pedido a petición del cliente, desde su enlace de seguimiento
     * (CU-06). Solo toca estado y cerrado_en, las dos columnas que puede
     * cambiar focaccia_web.
     *
     * @param  Pedido  $pedido  pedido que se cancela
     * @return bool false si ya no estaba pendiente: se aceptó o rechazó antes
     */
    public function cancelar(Pedido $pedido): bool
    {
        return $this->cambiar($pedido, 'pendiente', [
            'estado' => 'cancelado',
            'cerrado_en' => now(),
        ]);
    }

    /**
     * Pasos que puede dar el personal desde cada estado después de aceptar
     * (RF-13, CU-12): aceptado → listo; listo → recogido o no recogido.
     *
     * @var array<string, list<string>>
     */
    const SIGUIENTES = [
        'aceptado' => ['listo'],
        'listo' => ['recogido', 'no_recogido'],
    ];

    /**
     * Lleva el pedido al estado siguiente si el paso está permitido desde el
     * suyo. Al recogerlo se apunta el pago en el local; al recogerlo o no, se
     * cierra.
     *
     * @param  Pedido  $pedido  pedido que avanza
     * @param  string  $estado  listo, recogido o no_recogido
     * @return bool false si el paso no está permitido o el pedido cambió entre medias
     */
    public function avanzar(Pedido $pedido, string $estado): bool
    {
        if (! in_array($estado, self::SIGUIENTES[$pedido->estado] ?? [], true)) {
            return false;
        }

        $cambios = ['estado' => $estado];
        if ($estado === 'recogido') {
            $cambios['pagado_en'] = now();
        }
        if ($estado !== 'listo') {
            $cambios['cerrado_en'] = now();
        }

        return $this->cambiar($pedido, $pedido->estado, $cambios);
    }

    /**
     * Aplica los cambios si el pedido sigue en el estado de partida y deja el
     * modelo con lo que hay ahora en la base de datos.
     *
     * @param  Pedido  $pedido  pedido que cambia
     * @param  string  $desde  estado en el que tiene que estar
     * @param  array<string, mixed>  $cambios  valores que se guardan, por columna
     * @return bool true si se ha aplicado
     */
    private function cambiar(Pedido $pedido, string $desde, array $cambios): bool
    {
        $filas = DB::table('pedidos')
            ->where('id', $pedido->id)
            ->where('estado', $desde)
            ->update($cambios);

        $pedido->refresh();

        return $filas === 1;
    }
}
