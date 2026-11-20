<?php

/**
 * Orden que rechaza los pedidos pendientes que ya no se pueden recoger (decisión 030).
 */

namespace App\Console\Commands;

use App\Models\Horario;
use App\Models\Pedido;
use App\Models\Usuario;
use App\Servicios\Calendario;
use App\Servicios\CambiosDeEstado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * «php artisan pedidos:rechazar-pasados», que el planificador lanza cada 15
 * minutos. Un pedido pendiente está pasado cuando su día previsto ya terminó
 * o cuando es hoy y no le queda ninguna franja de recogida. Si el día
 * previsto no tiene turnos, se espera al día siguiente, por si alguien abre
 * un turno a última hora. El rechazo es el mismo cambio condicionado que usa
 * el personal: si alguien lo decide o el cliente lo cancela a la vez, no se pisan.
 */
class RechazarPedidosPasados extends Command
{
    /**
     * Nombre con el que se lanza la orden.
     *
     * @var string
     */
    protected $signature = 'pedidos:rechazar-pasados';

    /**
     * Texto que muestra «php artisan list».
     *
     * @var string
     */
    protected $description = 'Rechaza los pedidos pendientes que ya no se pueden recoger en su día previsto';

    /**
     * Motivo que recibe el cliente.
     *
     * @var string
     */
    const MOTIVO = 'No hemos podido revisar tu pedido a tiempo para el día previsto. '
        .'Perdona las molestias: puedes hacer otro pedido o llamarnos.';

    /**
     * Rechaza los pedidos pasados y dice cuántos ha rechazado.
     *
     * @param  Calendario  $calendario  franjas que le quedan a cada día
     * @param  CambiosDeEstado  $cambios  rechazo condicionado a que siga pendiente
     * @return int Command::SUCCESS
     */
    public function handle(Calendario $calendario, CambiosDeEstado $cambios): int
    {
        // como el personal: el usuario de la zona pública no puede rechazar pedidos
        DB::setDefaultConnection('gestion');

        $rechazados = 0;
        foreach ($this->pasados($calendario) as $pedido) {
            if ($cambios->rechazar($pedido, Usuario::rechazoAutomatico(), self::MOTIVO)) {
                $rechazados++;
            }
        }

        $this->info($rechazados === 1 ? 'Rechazado 1 pedido pasado.' : "Rechazados {$rechazados} pedidos pasados.");

        return self::SUCCESS;
    }

    /**
     * Pendientes de días anteriores y de hoy sin franjas, si hoy hay turnos.
     *
     * @param  Calendario  $calendario  franjas de hoy
     * @return list<Pedido>
     */
    private function pasados(Calendario $calendario): array
    {
        $pendientes = Pedido::where('estado', 'pendiente')->whereDate('fecha_prevista', '<=', today())->get();
        $hoyCerrado = Horario::whereDate('dia', today())->exists() && $calendario->franjas(today()) === [];

        return $pendientes
            ->filter(fn (Pedido $pedido) => $pedido->fecha_prevista->lt(today()) || $hoyCerrado)
            ->values()
            ->all();
    }
}
