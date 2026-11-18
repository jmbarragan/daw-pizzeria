<?php

/**
 * Datos de prueba del personal y de los clientes.
 */

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Usuario;
use Illuminate\Database\Seeder;

/**
 * Carga un administrador, tres empleados y uno dado de baja, y seis clientes:
 * dos registrados, tres invitados y uno que pidió por teléfono sin correo.
 * Todas las cuentas comparten la contraseña de prueba que figura en la guía
 * de uso; los teléfonos empiezan por +341 para que no correspondan a nadie.
 */
class PersonalYClientesSeeder extends Seeder
{
    /**
     * Contraseña de todas las cuentas de prueba (pública: va en la guía de uso).
     *
     * @var string
     */
    public const CLAVE_DE_PRUEBA = 'Focaccia.2026';

    /**
     * Crea el personal y los clientes con los identificadores del diseño.
     *
     * @param  CalendarioDePrueba  $calendario  fechas desplazadas al día de la instalación
     * @return void
     */
    public function run(CalendarioDePrueba $calendario): void
    {
        $personal = [
            [1, 'Marta Rossi', 'marta.rossi@example.com', Usuario::ROL_ADMINISTRADOR, true, '2026-10-01 09:00:00'],
            [2, 'Pablo Gil', 'pablo.gil@example.com', Usuario::ROL_EMPLEADO, true, '2026-10-01 09:05:00'],
            [3, 'Elena Navarro', 'elena.navarro@example.com', Usuario::ROL_EMPLEADO, true, '2026-10-01 09:10:00'],
            [4, 'Sergio Molina', 'sergio.molina@example.com', Usuario::ROL_EMPLEADO, true, '2026-10-01 09:15:00'],
            [5, 'Iván Castro', 'ivan.castro@example.com', Usuario::ROL_EMPLEADO, false, '2026-10-01 09:20:00'],
        ];
        foreach ($personal as [$id, $nombre, $correo, $rol, $activo, $alta]) {
            (new Usuario)->forceFill([
                'id' => $id, 'nombre' => $nombre, 'email' => $correo, 'contrasena' => self::CLAVE_DE_PRUEBA,
                'rol' => $rol, 'activo' => $activo, 'creado_en' => $calendario->momento($alta),
            ])->save();
        }

        // con fecha de verificación: registrado, con contraseña; sin ella: invitado
        $clientes = [
            [1, 'Lucía Fernández', '+34100111222', 'lucia.fernandez@example.com', '2026-11-03 21:00:00', '2026-11-03 20:05:00'],
            [2, 'Ana Martín', '+34100222333', 'ana.martin@example.com', null, '2026-11-05 20:30:00'],
            [3, 'Raúl Medina', '+34100333444', 'raul.medina@example.com', null, '2026-11-06 22:50:00'],
            [4, 'Sofía Herrera', '+34100444555', 'sofia.herrera@example.com', null, '2026-11-06 20:40:00'],
            [5, 'Jorge Ruiz', '+34100555666', 'jorge.ruiz@example.com', '2026-11-02 19:00:00', '2026-11-02 18:45:00'],
            [6, 'Antonio Vega', '+34100666777', null, null, '2026-11-04 21:00:00'],
        ];
        foreach ($clientes as [$id, $nombre, $telefono, $correo, $verificado, $alta]) {
            (new Cliente)->forceFill([
                'id' => $id, 'nombre' => $nombre, 'telefono' => $telefono, 'email' => $correo,
                'contrasena' => $verificado === null ? null : self::CLAVE_DE_PRUEBA,
                'email_verificado_en' => $verificado === null ? null : $calendario->momento($verificado),
                'creado_en' => $calendario->momento($alta),
            ])->save();
        }
    }
}
