<?php

/**
 * Datos de prueba de los ajustes del local y de los bloqueos de IP.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga los ocho ajustes del local, cuatro públicos y cuatro del servidor de
 * correo, y cinco bloqueos de IP con direcciones reservadas para ejemplos. La
 * 203.0.113.7 se bloquea dos veces en 30 días, así que el disparador deja la
 * segunda vez como permanente; el último bloqueo lo levantó la administradora.
 */
class AjustesYBloqueosSeeder extends Seeder
{
    /**
     * Inserta los ajustes y los bloqueos.
     *
     * @param  CalendarioDePrueba  $calendario  fechas desplazadas al día de la instalación
     * @return void
     */
    public function run(CalendarioDePrueba $calendario): void
    {
        $cambio = $calendario->momento('2026-10-01 09:30:00');
        $ajustes = [
            ['nombre_local', 'La Focaccia di Trevi', true, 'Nombre que aparece en la web y en los correos'],
            ['telefono_local', '+34100000000', true, 'Teléfono para pedidos grandes y dudas'],
            ['maximo_pizzas', '20', true, 'Pizzas por pedido a partir de las cuales hay que llamar al local'],
            ['politica_privacidad', 'Responsable: La Focaccia di Trevi. Finalidad: gestionar tu pedido y avisarte de su estado. '
                .'Conservamos tu teléfono y tu correo 30 días después de cerrar el pedido, salvo que tengas cuenta.',
                true, 'Texto de la política de privacidad'],
            ['smtp_servidor', 'smtp.gmail.com', false, 'Servidor de correo saliente'],
            ['smtp_puerto', '587', false, 'Puerto del servidor de correo (STARTTLS)'],
            ['smtp_usuario', 'pedidos.focaccia@example.com', false, 'Cuenta que envía los correos'],
        ];
        foreach ($ajustes as [$clave, $valor, $publico, $descripcion]) {
            DB::table('ajustes')->insert([
                'clave' => $clave, 'valor' => $valor, 'publico' => $publico, 'descripcion' => $descripcion,
                'actualizado_por' => 1, 'actualizado_en' => $cambio,
            ]);
        }
        // la contraseña del correo se pide al instalar: aquí queda vacía
        DB::table('ajustes')->insert([
            'clave' => 'smtp_contrasena', 'valor' => null, 'publico' => false,
            'descripcion' => 'Contraseña de aplicación del correo, cifrada',
        ]);

        // el orden importa: el disparador mira los bloqueos anteriores de la misma IP
        $bloqueos = [
            [1, '203.0.113.7', '2026-10-20 02:14:00'],
            [2, '198.51.100.23', '2026-10-28 11:40:00'],
            [3, '2001:db8::5', '2026-11-01 23:05:00'],
            [4, '203.0.113.7', '2026-11-02 03:30:00'],
            [5, '198.51.100.88', '2026-11-05 18:12:00'],
        ];
        foreach ($bloqueos as [$id, $ip, $inicio]) {
            DB::table('bloqueos_ip')->insert(['id' => $id, 'ip' => $ip, 'inicio' => $calendario->momento($inicio)]);
        }
        DB::table('bloqueos_ip')->where('id', 5)
            ->update(['desbloqueado_por' => 1, 'desbloqueado_en' => $calendario->momento('2026-11-05 19:00:00')]);
    }
}
