<?php

/**
 * Correo de aceptación de un pedido, con la hora y el lugar de recogida (RF-05, RF-14).
 */

namespace App\Notifications;

use App\Models\Cliente;
use App\Servicios\AjustesPublicos;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sale cuando el personal acepta un pedido de la web y cuando apunta uno por
 * teléfono con correo. La dirección es la del ajuste público direccion_local.
 */
class PedidoAceptado extends AvisoDePedido
{
    /**
     * Da la hora y el día de recogida y, si está en los ajustes, la dirección;
     * después, el resumen común.
     *
     * @param  Cliente  $cliente  destinatario
     * @return MailMessage
     */
    public function toMail(Cliente $cliente): MailMessage
    {
        $hora = $this->pedido->hora_recogida;
        $direccion = app(AjustesPublicos::class)->valor('direccion_local');
        $donde = $direccion ? " en {$direccion}" : '';

        return $this->resumen($this->mensaje($cliente, 'Tu pedido está aceptado')
            ->line("Ya estamos con tu pedido **{$this->pedido->codigo}**.")
            ->line("Recógelo hacia las **{$hora->format('H:i')}** del {$hora->format('d/m')}{$donde}."));
    }
}
