<?php

/**
 * Parte común de los correos que recibe el cliente sobre su pedido (RF-05, RNF-02).
 */

namespace App\Notifications;

use App\Models\Cliente;
use App\Models\Pedido;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Cada correo es un trabajo de la cola en base de datos: si el
 * servidor de correo no responde, el pedido ya está guardado y el envío se
 * intenta 3 veces: enseguida, al minuto y a los cinco minutos. Lo que no
 * salga queda en failed_jobs. Se encola solo cuando se confirma la transacción, así
 * que nunca sale un correo de un pedido que no se ha guardado.
 */
abstract class AvisoDePedido extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Intentos de envío antes de darlo por fallido.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Segundos de espera antes del segundo y del tercer intento.
     *
     * @var list<int>
     */
    public $backoff = [60, 300];

    /**
     * Guarda el pedido del aviso y espera a que se confirme la transacción.
     *
     * @param  Pedido  $pedido  pedido del que se avisa
     */
    public function __construct(public readonly Pedido $pedido)
    {
        $this->afterCommit();
    }

    /**
     * Encola el aviso para el cliente del pedido, si dejó correo; un pedido
     * telefónico puede no tenerlo.
     *
     * @return void
     */
    public function enviar(): void
    {
        $cliente = $this->pedido->cliente;

        if ($cliente->email) {
            $cliente->notify($this);
        }
    }

    /**
     * Se envía solo por correo.
     *
     * @param  Cliente  $cliente  destinatario
     * @return list<string>
     */
    public function via(Cliente $cliente): array
    {
        return ['mail'];
    }

    /**
     * Empieza el correo con el asunto, que termina en el código, y el saludo por
     * el nombre del cliente.
     *
     * @param  Cliente  $cliente  destinatario
     * @param  string  $asunto  asunto del correo, sin el código
     * @return MailMessage
     */
    protected function mensaje(Cliente $cliente, string $asunto): MailMessage
    {
        return (new MailMessage)
            ->subject("{$asunto} ({$this->pedido->codigo})")
            ->greeting('Hola, '.$this->sinFormato($cliente->nombre).':');
    }

    /**
     * Escapa los caracteres de Markdown de un texto que escribe el cliente, para
     * que salga tal cual y no como enlace, imagen o formato.
     *
     * @param  string  $texto  texto del cliente, por ejemplo su nombre
     * @return string
     */
    protected function sinFormato(string $texto): string
    {
        // los que abren enlaces, imágenes, código y énfasis; un guion o un punto en un nombre no hacen nada
        return addcslashes($texto, '\\`*_[]<>');
    }

    /**
     * Añade las líneas del pedido, el total y el enlace de seguimiento.
     *
     * @param  MailMessage  $mensaje  correo en preparación
     * @return MailMessage
     */
    protected function resumen(MailMessage $mensaje): MailMessage
    {
        $total = 0.0;
        foreach ($this->pedido->lineas as $linea) {
            $importe = $linea->cantidad * (float) $linea->precio_unitario;
            $total += $importe;
            $mensaje->line("{$linea->cantidad} × {$linea->nombre_producto}: ".$this->euros($importe));
        }

        // la dirección sale de APP_URL, no de la cabecera Host de una petición
        $enlace = rtrim(config('app.url'), '/').route('seguimiento', $this->pedido->token_seguimiento, false);

        return $mensaje
            ->line('**Total: '.$this->euros($total).'**, que se paga en el local al recogerlo.')
            ->action('Ver el seguimiento del pedido', $enlace)
            ->salutation('La Focaccia di Trevi');
    }

    /**
     * Importe con coma decimal y el símbolo del euro.
     *
     * @param  float  $importe  importe en euros
     * @return string
     */
    private function euros(float $importe): string
    {
        return number_format($importe, 2, ',', '.').' €';
    }
}
