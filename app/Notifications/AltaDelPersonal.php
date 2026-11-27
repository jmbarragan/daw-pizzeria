<?php

/**
 * Correo de bienvenida a una persona del personal recién dada de alta (RF-21, CU-20).
 */

namespace App\Notifications;

use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * En lugar de una contraseña inicial, lleva un enlace para que el empleado
 * elija la suya: nadie más llega a conocerla. Reutiliza el
 * token y la pantalla de la recuperación, así que sirve 60 minutos; si caduca,
 * se pide otro desde «¿Has olvidado la contraseña?».
 */
class AltaDelPersonal extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Intentos de envío, como los avisos del pedido: si el correo no responde, el alta ya está hecha.
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
     * @param  string  $token  token del gestor de contraseñas del personal
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
     * Da la bienvenida, dice con qué rol entra y lleva a elegir la contraseña, con la dirección de APP_URL.
     *
     * @param  Usuario  $usuario  destinatario
     * @return MailMessage
     */
    public function toMail(Usuario $usuario): MailMessage
    {
        $ruta = route('gestion.contrasena.nueva', ['token' => $this->token, 'email' => $usuario->email], false);
        $rol = $usuario->esAdministrador() ? 'administrador' : 'empleado';
        $minutos = config('auth.passwords.personal.expire');

        return (new MailMessage)
            ->subject('Tu cuenta en la gestión de La Focaccia di Trevi')
            ->greeting("Hola, {$usuario->nombre}:")
            ->line("Ya tienes cuenta en la gestión de la pizzería, como {$rol}. Para entrar, elige tu contraseña.")
            ->action('Elegir mi contraseña', rtrim(config('app.url'), '/').$ruta)
            ->line("El enlace sirve durante {$minutos} minutos. Si caduca, pide otro en «¿Has olvidado la contraseña?».")
            ->salutation('La Focaccia di Trevi');
    }
}
