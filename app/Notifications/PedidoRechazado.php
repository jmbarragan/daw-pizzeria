<?php

/**
 * Correo de rechazo de un pedido, con el motivo (RF-05, RF-12).
 */

namespace App\Notifications;

use App\Models\Cliente;
use App\Servicios\AjustesPublicos;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sale cuando el personal rechaza el pedido y cuando lo rechaza la orden
 * automática por haberse pasado su día. Da el motivo y el teléfono del local.
 */
class PedidoRechazado extends AvisoDePedido
{
    /**
     * Explica que no se preparará, con el motivo y, si está en los ajustes, el
     * teléfono del local; después, el resumen común.
     *
     * @param  Cliente  $cliente  destinatario
     * @return MailMessage
     */
    public function toMail(Cliente $cliente): MailMessage
    {
        $mensaje = $this->mensaje($cliente, 'No podemos preparar tu pedido')
            ->line("Lo sentimos: no podemos preparar tu pedido **{$this->pedido->codigo}**.")
            ->line("Motivo: {$this->pedido->motivo_rechazo}");

        $telefono = app(AjustesPublicos::class)->valor('telefono_local');
        if ($telefono) {
            $mensaje->line("Si tienes cualquier duda, llámanos al {$telefono}.");
        }

        return $this->resumen($mensaje);
    }
}
