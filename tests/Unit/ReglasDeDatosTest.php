<?php

/**
 * Pruebas unitarias de las reglas de validación de los tipos de datos.
 */

namespace Tests\Unit;

use App\Validacion\ReglasDeDatos;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cada caso es una entrada y la salida esperada: aceptada (true) o rechazada
 * (false). Los nombres de los casos son los que se copian a la tabla del
 * informe de pruebas. No tocan la base de datos; necesitan la aplicación solo
 * para el validador de Laravel.
 */
class ReglasDeDatosTest extends TestCase
{
    /**
     * Correos bien formados frente a los que les falta una parte, llevan un
     * espacio, un comentario o comillas, o pasan de los 254 caracteres de la columna.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function correos(): array
    {
        return [
            'correo normal' => ['ana.martin@example.com', true],
            'dominio corto' => ['a@b.es', true],
            'sin arroba' => ['ana.martin', false],
            'sin dominio' => ['ana@', false],
            'sin usuario' => ['@example.com', false],
            'con un espacio' => ['ana martin@example.com', false],
            'con un comentario' => ['ana.martin(casa)@example.com', false],
            'entre comillas' => ['"ana.martin"@example.com', false],
            'de 257 caracteres' => [str_repeat('a', 245).'@example.com', false],
        ];
    }

    /**
     * Teléfonos E.164: el límite está en 15 cifras; sin «+», con espacios o
     * empezando por 0 no valen. Los españoles empiezan por +341, que no se
     * asigna a nadie, para no usar números de personas reales.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function telefonos(): array
    {
        return [
            'número español' => ['+34100123456', true],
            'número de otro país' => ['+12025550123', true],
            '15 cifras, el máximo' => ['+341001234567890', true],
            'sin el +' => ['100123456', false],
            'con espacios' => ['+34 100 123 456', false],
            'empieza por 0' => ['+0123456789', false],
            '16 cifras' => ['+3410012345678901', false],
            'demasiado corto' => ['+34100', false],
        ];
    }

    /**
     * Precios en los bordes de DECIMAL(5,2): de 0,01 a 999,99 y con dos
     * decimales como mucho. Llegan como texto del formulario o como número.
     *
     * @return array<string, array{0: string|int|float, 1: bool}>
     */
    public static function precios(): array
    {
        return [
            'con dos decimales' => ['9.50', true],
            'entero' => [12, true],
            'el más bajo' => [0.01, true],
            'el más alto' => ['999.99', true],
            'cero' => ['0', false],
            'negativo' => ['-1', false],
            'tres decimales' => ['9.505', false],
            'no cabe en DECIMAL(5,2)' => ['1000', false],
            'texto' => ['nueve', false],
        ];
    }

    /**
     * Códigos de seguimiento: el del ejemplo del diseño y variantes con un
     * carácter de menos, en minúsculas o fuera del alfabeto hexadecimal.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function codigos(): array
    {
        return [
            'código del diseño' => ['7B3E0A26', true],
            'solo cifras' => ['12345626', true],
            'en minúsculas' => ['7b3e0a26', false],
            'siete caracteres' => ['7B3E0A2', false],
            'letra que no es hexadecimal' => ['7B3E0G26', false],
            'año con una letra' => ['7B3E0A2X', false],
        ];
    }

    /**
     * Horas de turno en HH:MM de 24 horas, que es lo que envía el campo de hora
     * del navegador; sin segundos y con las dos cifras de la hora.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function horas(): array
    {
        return [
            'hora de la cena' => ['20:30', true],
            'último minuto del día' => ['23:59', true],
            'medianoche' => ['00:00', true],
            'hora de una cifra' => ['9:30', false],
            'con segundos' => ['20:30:00', false],
            'las 24:00' => ['24:00', false],
            'minuto 60' => ['20:60', false],
            'texto' => ['por la noche', false],
        ];
    }

    /**
     * Nombres de categoría, de 2 a 40 caracteres. Se usa «ñ», que ocupa dos
     * bytes, para ver que se cuentan caracteres.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function nombres(): array
    {
        return [
            'nombre corto' => ['Pizzas', true],
            '40 letras con tilde' => [str_repeat('ñ', 40), true],
            'una sola letra' => ['P', false],
            '41 letras' => [str_repeat('ñ', 41), false],
        ];
    }

    /**
     * Pasa un valor por el validador de Laravel con unas reglas.
     *
     * @param  mixed  $valor  dato que se valida
     * @param  list<mixed>  $reglas  reglas de ReglasDeDatos
     * @return bool true si el validador lo acepta
     */
    private function acepta(mixed $valor, array $reglas): bool
    {
        return Validator::make(['dato' => $valor], ['dato' => $reglas])->passes();
    }

    /**
     * La regla de correo acepta o rechaza cada caso de correos().
     *
     * @param  string  $entrada  correo probado
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('correos')]
    public function test_correo(string $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::correo()));
    }

    /**
     * La regla de teléfono acepta o rechaza cada caso de telefonos().
     *
     * @param  string  $entrada  teléfono probado
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('telefonos')]
    public function test_telefono(string $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::telefono()));
    }

    /**
     * La regla de precio acepta o rechaza cada caso de precios().
     *
     * @param  string|int|float  $entrada  precio probado
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('precios')]
    public function test_precio(string|int|float $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::precio()));
    }

    /**
     * La regla del código de pedido acepta o rechaza cada caso de codigos().
     *
     * @param  string  $entrada  código probado
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('codigos')]
    public function test_codigo_de_pedido(string $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::codigoDePedido()));
    }

    /**
     * La regla de hora acepta o rechaza cada caso de horas().
     *
     * @param  string  $entrada  hora probada
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('horas')]
    public function test_hora(string $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::hora()));
    }

    /**
     * La regla de nombre con 40 caracteres de máximo acepta o rechaza cada
     * caso de nombres().
     *
     * @param  string  $entrada  nombre probado
     * @param  bool  $esperado  si debe aceptarse
     * @return void
     */
    #[DataProvider('nombres')]
    public function test_nombre(string $entrada, bool $esperado): void
    {
        $this->assertSame($esperado, $this->acepta($entrada, ReglasDeDatos::nombre(40)));
    }
}
