<?php

/**
 * Pruebas unitarias del paso de un teléfono escrito a mano a E.164.
 */

namespace Tests\Unit;

use App\Validacion\Telefono;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cada caso es lo que escribe el cliente y lo que se guarda (o se valida
 * después). Los números españoles empiezan por +341, que no se asigna a nadie.
 */
class TelefonoTest extends TestCase
{
    /**
     * Entrada del formulario y salida esperada.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function casos(): array
    {
        return [
            'nueve cifras seguidas' => ['100123456', '+34100123456'],
            'con espacios' => ['100 12 34 56', '+34100123456'],
            'con puntos' => ['100.123.456', '+34100123456'],
            'con guiones' => ['100-123-456', '+34100123456'],
            'con el +34 y espacios' => ['+34 100 123 456', '+34100123456'],
            'con paréntesis en el prefijo' => ['(+34) 100 123 456', '+34100123456'],
            'número de otro país' => ['+1 202 555 0123', '+12025550123'],
            'ocho cifras: se deja para que la regla lo rechace' => ['10012345', '10012345'],
            'empieza por 0: tampoco se toca' => ['012345678', '012345678'],
            'vacío' => ['', ''],
        ];
    }

    /**
     * Normaliza cada caso.
     *
     * @param  string  $escrito  lo que escribe el cliente
     * @param  string  $esperado  lo que debe quedar
     * @return void
     */
    #[DataProvider('casos')]
    public function test_normalizar(string $escrito, string $esperado): void
    {
        $this->assertSame($esperado, Telefono::normalizar($escrito));
    }
}
