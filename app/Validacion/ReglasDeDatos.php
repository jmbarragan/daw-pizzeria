<?php

/**
 * Reglas de validación de los datos que se repiten en varios formularios.
 */

namespace App\Validacion;

/**
 * Cada método devuelve las reglas de Laravel de un tipo de dato del diseño,
 * con los mismos límites que las columnas de la base de datos. Así un dato
 * mal formado se rechaza en el formulario, con su mensaje, antes de que
 * MySQL lo rechace con un error.
 */
final class ReglasDeDatos
{
    /**
     * Precio más alto que cabe en DECIMAL(5,2).
     *
     * @var string
     */
    const PRECIO_MAXIMO = '999.99';

    /**
     * Correo electrónico: formato RFC sin las formas raras que la norma tolera
     * con avisos (comentarios entre paréntesis, partes entre comillas), y como
     * mucho 254 caracteres, el límite de la columna y de la propia norma. Así
     * una misma dirección no se puede escribir de varias maneras para saltarse
     * el límite de pedidos por correo (decisión 031).
     *
     * @return list<string>
     */
    public static function correo(): array
    {
        return ['string', 'max:254', 'email:strict'];
    }

    /**
     * Teléfono en formato E.164: «+», un primer dígito que no sea 0 y hasta 15
     * cifras en total, sin espacios. Ejemplo: +34600123456.
     *
     * @return list<string>
     */
    public static function telefono(): array
    {
        return ['string', 'regex:/^\+[1-9][0-9]{7,14}$/'];
    }

    /**
     * Precio con IVA: mayor que cero, como mucho dos decimales y que quepa en
     * DECIMAL(5,2).
     *
     * @return list<string>
     */
    public static function precio(): array
    {
        return ['numeric', 'decimal:0,2', 'gt:0', 'max:'.self::PRECIO_MAXIMO];
    }

    /**
     * Código de seguimiento del pedido: seis hexadecimales en mayúsculas y las
     * dos últimas cifras del año (RF-04). Ejemplo: 7B3E0A26.
     *
     * @return list<string>
     */
    public static function codigoDePedido(): array
    {
        return ['string', 'regex:/^[0-9A-F]{6}[0-9]{2}$/'];
    }

    /**
     * Hora de un turno en formato HH:MM de 24 horas, como la envía el campo
     * de hora del navegador. Ejemplo: 20:30.
     *
     * @return list<string>
     */
    public static function hora(): array
    {
        return ['string', 'date_format:H:i'];
    }

    /**
     * Nombre de texto libre con la longitud de su columna: 40 para categorías
     * y alérgenos, 60 para productos y 80 para personas.
     *
     * @param  int  $maximo  número máximo de caracteres
     * @return list<string>
     */
    public static function nombre(int $maximo): array
    {
        return ['string', 'min:2', "max:{$maximo}"];
    }
}
