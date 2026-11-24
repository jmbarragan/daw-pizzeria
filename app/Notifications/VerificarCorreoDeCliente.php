<?php

/**
 * Correo para verificar la dirección de un cliente que se acaba de registrar (RF-08, CU-07).
 */

namespace App\Notifications;

use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * El enlace va firmado con la clave de la aplicación y sirve 24 horas. Lleva
 * el identificador del cliente y el hash de su correo: si cambia el correo,
 * el enlace deja de valer. Hasta abrirlo no se puede iniciar sesión.
 */
class VerificarCorreoDeCliente extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Horas que sirve el enlace.
     *
     * @var int
     */
    const HORAS = 24;

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
     * Espera a que se confirme la transacción del registro.
     */
    public function __construct()
    {
        $this->afterCommit();
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
     * Explica para qué es el enlace y cuánto dura.
     *
     * @param  Cliente  $cliente  destinatario
     * @return MailMessage
     */
    public function toMail(Cliente $cliente): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirma tu correo en La Focaccia di Trevi')
            ->greeting('Hola:')
            ->line('Has creado una cuenta para ver tus pedidos y repetirlos. Falta un paso: confirmar que este correo es tuyo.')
            ->action('Confirmar el correo', self::enlace($cliente))
            ->line('El enlace sirve durante '.self::HORAS.' horas. Si no has sido tú, no hagas nada: sin confirmarlo, nadie puede entrar en la cuenta.')
            ->salutation('La Focaccia di Trevi');
    }

    /**
     * Dirección firmada de la verificación. Se firma sin el dominio y se le
     * pone el de APP_URL, para no depender de la cabecera Host de nadie.
     *
     * @param  Cliente  $cliente  cliente que se verifica
     * @return string
     */
    public static function enlace(Cliente $cliente): string
    {
        $ruta = URL::temporarySignedRoute('cuenta.verificar', now()->addHours(self::HORAS), [
            'cliente' => $cliente->id,
            'hash' => sha1((string) $cliente->email),
        ], false);

        return rtrim(config('app.url'), '/').$ruta;
    }
}
