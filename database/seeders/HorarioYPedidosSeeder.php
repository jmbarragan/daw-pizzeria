<?php

/**
 * Datos de prueba del calendario de apertura y de los pedidos con sus líneas.
 */

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Carga una semana de turnos (martes a viernes cena; sábado y domingo comida y
 * cena; lunes cerrado) y ocho pedidos: uno en cada estado más uno telefónico.
 * Con el calendario desplazado, el viernes de los datos es el día de la
 * instalación, así que hay un pedido pendiente, uno aceptado y uno listo para
 * recoger en el panel del día.
 */
class HorarioYPedidosSeeder extends Seeder
{
    /**
     * Inserta turnos, pedidos y líneas.
     *
     * @param  CalendarioDePrueba  $calendario  fechas desplazadas al día de la instalación
     * @return void
     */
    public function run(CalendarioDePrueba $calendario): void
    {
        $turnos = [
            [1, '2026-11-03', 1, '20:00:00', '23:30:00'],
            [2, '2026-11-04', 1, '20:00:00', '23:30:00'],
            [3, '2026-11-05', 1, '20:00:00', '23:30:00'],
            [4, '2026-11-06', 1, '20:00:00', '23:30:00'],
            [5, '2026-11-07', 1, '13:00:00', '16:00:00'],
            [6, '2026-11-07', 2, '20:00:00', '23:30:00'],
            [7, '2026-11-08', 1, '13:00:00', '16:00:00'],
            [8, '2026-11-08', 2, '20:00:00', '23:30:00'],
        ];
        foreach ($turnos as [$id, $dia, $turno, $apertura, $cierre]) {
            DB::table('horarios')->insert([
                'id' => $id, 'dia' => $calendario->dia($dia), 'turno' => $turno,
                'hora_apertura' => $apertura, 'hora_cierre' => $cierre,
            ]);
        }

        // id, código del diseño, cliente, empleado, turno, canal, estado, observaciones, motivo,
        // creado, recogida prevista, decidido, hora de recogida, cerrado, pagado
        $pedidos = [
            [1, '7B3E0A26', 1, 2, 1, 'web', 'recogido', null, null,
                '2026-11-03 20:05:00', '2026-11-03', '2026-11-03 20:08:00', '2026-11-03 20:45:00', '2026-11-03 20:52:00', '2026-11-03 20:52:00'],
            [2, 'C41D9F26', 6, 3, 2, 'telefono', 'recogido', null, null,
                '2026-11-04 21:00:00', '2026-11-04', '2026-11-04 21:00:00', '2026-11-04 21:30:00', '2026-11-04 21:34:00', '2026-11-04 21:34:00'],
            [3, '0E8A5226', 2, 2, 3, 'web', 'no_recogido', null, null,
                '2026-11-05 20:30:00', '2026-11-05', '2026-11-05 20:33:00', '2026-11-05 21:15:00', '2026-11-05 23:30:00', null],
            [4, 'A9F31C26', 3, 4, null, 'web', 'rechazado', null, 'Horno completo hasta el cierre; no podemos prepararlo a tiempo.',
                '2026-11-06 22:50:00', '2026-11-06', '2026-11-06 22:55:00', null, '2026-11-06 22:55:00', null],
            [5, '5D2B7E26', 4, 3, 4, 'web', 'listo', null, null,
                '2026-11-06 20:40:00', '2026-11-06', '2026-11-06 20:42:00', '2026-11-06 21:20:00', null, null],
            [6, 'E07C4A26', 1, 2, 4, 'web', 'aceptado', 'Bien hecha, por favor', null,
                '2026-11-06 21:04:00', '2026-11-06', '2026-11-06 21:06:00', '2026-11-06 21:45:00', null, null],
            [7, '91F6D326', 5, null, null, 'web', 'pendiente', null, null,
                '2026-11-06 21:10:00', '2026-11-06', null, null, null, null],
            [8, '2C8E1B26', 3, null, null, 'web', 'cancelado', null, null,
                '2026-11-07 13:05:00', '2026-11-07', null, null, '2026-11-07 13:07:00', null],
        ];
        $momento = fn (?string $fecha): ?string => $fecha === null ? null : $calendario->momento($fecha);
        foreach ($pedidos as [$id, $codigo, $cliente, $usuario, $turno, $canal, $estado, $notas, $motivo,
            $creado, $prevista, $decidido, $recogida, $cerrado, $pagado]) {
            $codigo = $calendario->codigo($codigo, $calendario->dia($prevista));
            $creado = $momento($creado);
            DB::table('pedidos')->insert([
                'id' => $id, 'codigo' => $codigo, 'cliente_id' => $cliente, 'usuario_id' => $usuario,
                'horario_id' => $turno, 'canal' => $canal, 'estado' => $estado,
                // enlace de seguimiento (RF-05): HMAC del código y la marca de creación con la clave de la aplicación
                'token_seguimiento' => hash_hmac('sha256', $codigo.':'.Carbon::parse($creado)->timestamp, (string) config('app.key')),
                'observaciones' => $notas, 'motivo_rechazo' => $motivo, 'creado_en' => $creado,
                'fecha_prevista' => $calendario->dia($prevista), 'decidido_en' => $momento($decidido),
                'hora_recogida' => $momento($recogida), 'cerrado_en' => $momento($cerrado), 'pagado_en' => $momento($pagado),
            ]);
        }

        // el pedido 1 conserva la Margherita a 9,00 y la Limonada, hoy retirada
        $lineas = [
            [1, 1, 'Margherita', 9.00, 2], [1, 9, 'Limonada casera', 2.50, 1],
            [2, 2, 'Diavola', 11.00, 1], [3, 3, 'Quattro formaggi', 12.00, 1],
            [4, 5, 'Focaccia di Trevi', 13.50, 1], [5, 7, 'Bruschetta', 5.50, 2],
            [6, 1, 'Margherita', 9.50, 2], [6, 8, 'Agua mineral 50 cl', 1.50, 1],
            [7, 2, 'Diavola', 11.00, 2], [8, 10, 'Tiramisú', 4.50, 1],
        ];
        foreach ($lineas as [$pedido, $producto, $nombre, $precio, $cantidad]) {
            DB::table('lineas_pedido')->insert([
                'pedido_id' => $pedido, 'producto_id' => $producto, 'nombre_producto' => $nombre,
                'precio_unitario' => $precio, 'cantidad' => $cantidad,
            ]);
        }
    }
}
