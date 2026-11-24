<?php

/**
 * Correo con el enlace para que un cliente con cuenta elija otra contraseña (RF-08).
 */

namespace App\Notifications;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Igual que el del personal, pero lleva a la zona pública. El token es de un
 * solo uso y caduca a los 60 minutos (config/auth.php, gestor «clientes»).
 */
class RecuperarContrasenaDeCliente extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Intentos de envío, como los avisos del pedido.
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
     * Guarda el token que irá en el enlace.
     *
     * @param  string  $token  token que genera el gestor de contraseñas
     */
    public function __construct(public readonly string $token) {}

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
     * Botón con el enlace a la pantalla de contraseña nueva, con la dirección de APP_URL.
     *
     * @param  Cliente  $cliente  destinatario
     * @return MailMessage
     */
    public function toMail(Cliente $cliente): MailMessage
    {
        $ruta = route('contrasena.nueva', ['token' => $this->token, 'email' => $cliente->email], false);
        $minutos = config('auth.passwords.clientes.expire');

        return (new MailMessage)
            ->subject('Cambia tu contraseña de La Focaccia di Trevi')
            ->greeting('Hola:')
            ->line('Alguien ha pedido cambiar la contraseña de tu cuenta de cliente.')
            ->action('Elegir una contraseña nueva', rtrim(config('app.url'), '/').$ruta)
            ->line("El enlace sirve durante {$minutos} minutos y una sola vez.")
            ->line('Si no lo has pedido tú, no hagas nada: tu contraseña sigue siendo la misma.')
            ->salutation('La Focaccia di Trevi');
    }
}
