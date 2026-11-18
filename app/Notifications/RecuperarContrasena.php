<?php

/**
 * Correo con el enlace para que el personal elija una contraseña nueva (RF-10).
 */

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sustituye al correo de recuperación de Laravel para que llegue en español y
 * con el tono del resto de la aplicación. El enlace lleva el token de un solo
 * uso y caduca a los 60 minutos (config/auth.php).
 */
class RecuperarContrasena extends Notification
{
    /**
     * Guarda el token que irá en el enlace.
     *
     * @param  string  $token  token de recuperación que genera el gestor de contraseñas
     */
    public function __construct(public readonly string $token) {}

    /**
     * Se envía solo por correo.
     *
     * @param  Usuario  $usuario  destinatario
     * @return list<string>
     */
    public function via(Usuario $usuario): array
    {
        return ['mail'];
    }

    /**
     * Asunto, explicación y botón con el enlace a la pantalla de contraseña nueva.
     *
     * @param  Usuario  $usuario  destinatario
     * @return MailMessage
     */
    public function toMail(Usuario $usuario): MailMessage
    {
        // la dirección sale de APP_URL y no de la petición: con otra cabecera Host
        // alguien podría hacer que el enlace, con el token, apuntara a su servidor
        $ruta = route('gestion.contrasena.nueva', ['token' => $this->token, 'email' => $usuario->email], false);
        $enlace = rtrim(config('app.url'), '/').$ruta;
        $minutos = config('auth.passwords.personal.expire');

        return (new MailMessage)
            ->subject('Cambia tu contraseña de La Focaccia di Trevi')
            ->greeting("Hola, {$usuario->nombre}:")
            ->line('Alguien ha pedido cambiar la contraseña con la que entras en la gestión de la pizzería.')
            ->action('Elegir una contraseña nueva', $enlace)
            ->line("El enlace sirve durante {$minutos} minutos y una sola vez.")
            ->line('Si no lo has pedido tú, no hagas nada: tu contraseña sigue siendo la misma.')
            ->salutation('La Focaccia di Trevi');
    }
}
