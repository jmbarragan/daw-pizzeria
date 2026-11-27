<?php

/**
 * Servidor de correo saliente tomado de los ajustes del local (RF-21).
 */

namespace App\Servicios;

use App\Models\Ajuste;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\QueryException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Antes de cada correo, si están los cuatro ajustes smtp_* (servidor, puerto,
 * usuario y contraseña), el envío va por ese servidor; si falta alguno, se
 * queda con las variables MAIL_* del .env. Así, los datos de prueba, que no
 * traen contraseña, siguen mandando a Mailpit. Lee con la conexión por
 * defecto: la cola trabaja con «gestion», que puede leer los ajustes; la zona
 * pública no puede, y entonces también se queda con el .env.
 */
class CorreoSaliente
{
    /**
     * Ajustes que forman el servidor de correo.
     *
     * @var list<string>
     */
    const CLAVES = ['smtp_servidor', 'smtp_puerto', 'smtp_usuario', 'smtp_contrasena'];

    /**
     * Prepara el servicio con el gestor de correo de Laravel.
     *
     * @param  MailManager  $correo  gestor que guarda los transportes ya creados
     */
    public function __construct(private readonly MailManager $correo) {}

    /**
     * Pone el servidor de los ajustes en el transporte smtp, si se usa smtp y
     * los ajustes están completos.
     *
     * @return bool verdadero si el correo saldrá por el servidor de los ajustes
     */
    public function aplicar(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return false;
        }

        $ajustes = $this->leer();
        if (count(array_filter($ajustes, fn (?string $valor) => $valor !== null && $valor !== '')) < count(self::CLAVES)) {
            return false;
        }

        try {
            $contrasena = Crypt::decryptString((string) $ajustes['smtp_contrasena']);
        } catch (DecryptException) {
            // se cifró con otra clave de la aplicación: mejor el .env que un envío que nunca va a entrar
            Log::warning('La contraseña del correo de los ajustes no se puede descifrar; se usan las variables MAIL_*.');

            return false;
        }

        $puerto = (int) $ajustes['smtp_puerto'];
        config([
            'mail.mailers.smtp.host' => $ajustes['smtp_servidor'],
            'mail.mailers.smtp.port' => $puerto,
            'mail.mailers.smtp.username' => $ajustes['smtp_usuario'],
            'mail.mailers.smtp.password' => $contrasena,
            // 465 es SMTP sobre TLS desde el principio; el resto, STARTTLS si el servidor lo ofrece
            'mail.mailers.smtp.scheme' => $puerto === 465 ? 'smtps' : 'smtp',
        ]);
        // el transporte ya creado se descarta para que el siguiente correo use estos datos
        $this->correo->purge('smtp');

        return true;
    }

    /**
     * Valores de los cuatro ajustes, o todos a null si la conexión no puede leerlos.
     *
     * @return array<string, string|null>
     */
    private function leer(): array
    {
        $vacios = array_fill_keys(self::CLAVES, null);

        try {
            return Ajuste::whereIn('clave', self::CLAVES)->pluck('valor', 'clave')->all() + $vacios;
        } catch (QueryException) {
            return $vacios;
        }
    }
}
