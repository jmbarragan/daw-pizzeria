<?php

/**
 * Correo de recepción de un pedido de la web (RF-05).
 */

namespace App\Notifications;

use App\Models\Cliente;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sale al enviar el pedido, que todavía está pendiente: explica que se revisará
 * y que llegará otro correo con la hora de recogida.
 */
class PedidoRecibido extends AvisoDePedido
{
    /**
     * Confirma el código y el día previsto y avisa de que la hora llegará en
     * otro correo; después, el resumen común.
     *
     * @param  Cliente  $cliente  destinatario
     * @return MailMessage
     */
    public function toMail(Cliente $cliente): MailMessage
    {
        $dia = $this->pedido->fecha_prevista->locale('es')->isoFormat('dddd D/MM');

        return $this->resumen($this->mensaje($cliente, 'Hemos recibido tu pedido')
            ->line("Tenemos tu pedido **{$this->pedido->codigo}** para recoger el {$dia}.")
            ->line('Lo revisaremos enseguida y te escribiremos al aceptarlo, con la hora de recogida.'));
    }
}
