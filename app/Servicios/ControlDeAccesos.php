<?php

/**
 * Cuenta los accesos fallidos por IP y bloquea la IP cuando son demasiados (RNF-01).
 */

namespace App\Servicios;

use App\Models\BloqueoIp;
use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Los fallos se cuentan en la caché, no en la base de datos: el diseño solo
 * guarda el bloqueo. Cada IP tiene una sola entrada con los fallos de cada
 * correo probado desde ella, que dura 24 horas desde el primer fallo. Al
 * llegar la suma a diez se crea el bloqueo, y el disparador de bloqueos_ip
 * decide si dura 24 horas o es permanente.
 *
 * Un acceso correcto solo borra los fallos de la cuenta que ha entrado
 * (decisión 023). Si pusiera a cero toda la IP, alguien con una cuenta propia
 * podría probar nueve contraseñas de otra persona, entrar con la suya y volver
 * a empezar. Y como todo va en la misma entrada, los fallos de una cuenta
 * caducan a la vez que los de la IP: no sobreviven a la ventana para restarse
 * en la siguiente.
 *
 * Lo usan el acceso del personal y, más adelante, el de los clientes.
 */
class ControlDeAccesos
{
    /**
     * Fallos desde una IP que la bloquean.
     *
     * @var int
     */
    const FALLOS_PARA_BLOQUEAR = 10;

    /**
     * Segundos durante los que se acumulan los fallos: 24 horas.
     *
     * @var int
     */
    const VENTANA_DE_FALLOS = 86400;

    /**
     * Bloqueo en vigor de una IP, si lo tiene.
     *
     * @param  string  $ip  IP de la petición
     * @return BloqueoIp|null
     */
    public function bloqueoVigente(string $ip): ?BloqueoIp
    {
        return BloqueoIp::vigentes()->where('ip', $ip)->latest('inicio')->first();
    }

    /**
     * Suma un fallo al correo probado y, si la IP llega a diez, la bloquea.
     *
     * @param  string  $ip  IP de la petición
     * @param  string  $email  correo con el que se ha intentado entrar
     * @return BloqueoIp|null el bloqueo recién creado, o null si aún no toca
     */
    public function registrarFallo(string $ip, string $email): ?BloqueoIp
    {
        $cuenta = $this->resumen($email);
        $bloquear = false;

        $this->cambiarFallos($ip, function (array $fallos) use ($cuenta, &$bloquear) {
            $fallos[$cuenta] = ($fallos[$cuenta] ?? 0) + 1;
            $bloquear = array_sum($fallos) >= self::FALLOS_PARA_BLOQUEAR;

            // con el bloqueo empieza una cuenta nueva
            return $bloquear ? [] : $fallos;
        });

        if (! $bloquear) {
            return null;
        }

        // inicio va desde PHP para que el fin que calcula el disparador use la misma hora
        return BloqueoIp::create(['ip' => $ip, 'inicio' => now()])->refresh();
    }

    /**
     * Tras un acceso correcto, borra los fallos de esa cuenta y deja los demás.
     *
     * @param  string  $ip  IP de la petición
     * @param  string  $email  correo con el que se ha entrado
     * @return void
     */
    public function registrarAcceso(string $ip, string $email): void
    {
        $cuenta = $this->resumen($email);

        $this->cambiarFallos($ip, function (array $fallos) use ($cuenta) {
            unset($fallos[$cuenta]);

            return $fallos;
        });
    }

    /**
     * Lee, cambia y guarda los fallos de una IP con un candado, para que dos
     * peticiones a la vez no se pisen la cuenta. La entrada conserva la hora
     * del primer fallo y caduca 24 horas después.
     *
     * @param  string  $ip  IP de la petición
     * @param  Closure(array<string, int>): array<string, int>  $cambio  recibe los fallos y devuelve los nuevos
     * @return void
     */
    private function cambiarFallos(string $ip, Closure $cambio): void
    {
        $clave = 'fallos-de-acceso:'.$ip;

        Cache::lock($clave.':candado', 5)->block(5, function () use ($clave, $cambio) {
            $entrada = Cache::get($clave, ['desde' => now()->getTimestamp(), 'fallos' => []]);
            $fallos = $cambio($entrada['fallos']);
            $quedan = $entrada['desde'] + self::VENTANA_DE_FALLOS - now()->getTimestamp();

            if ($fallos === [] || $quedan <= 0) {
                Cache::forget($clave);
            } else {
                Cache::put($clave, ['desde' => $entrada['desde'], 'fallos' => $fallos], $quedan);
            }
        });
    }

    /**
     * Resumen sha256 del correo, para no guardarlo tal cual en la caché.
     *
     * @param  string  $email  correo probado
     * @return string
     */
    private function resumen(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }
}
