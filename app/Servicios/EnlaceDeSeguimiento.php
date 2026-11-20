<?php

/**
 * Enlace de seguimiento de un pedido (RF-05).
 */

namespace App\Servicios;

use App\Models\Pedido;
use Carbon\CarbonInterface;

/**
 * El enlace lleva un token de 64 caracteres: el HMAC-SHA256 del código y de la
 * marca UNIX de creación, firmado con la clave de la aplicación. Sin la clave
 * no se puede fabricar uno válido, y no cambia con el estado del pedido. Es la
 * misma fórmula que usan los datos de prueba.
 */
class EnlaceDeSeguimiento
{
    /**
     * Firma el código y la hora de creación de un pedido. Lo usa el registro
     * del pedido antes de guardarlo, porque la columna no admite nulos.
     *
     * @param  string  $codigo  código de 8 caracteres del pedido
     * @param  CarbonInterface  $creadoEn  momento de creación, sin fracciones de segundo
     * @return string 64 caracteres hexadecimales en minúsculas
     */
    public function calcular(string $codigo, CarbonInterface $creadoEn): string
    {
        return hash_hmac('sha256', $codigo.':'.$creadoEn->getTimestamp(), (string) config('app.key'));
    }

    /**
     * Dirección completa de la página de seguimiento de un pedido.
     *
     * @param  Pedido  $pedido  pedido guardado
     * @return string
     */
    public function direccion(Pedido $pedido): string
    {
        return route('seguimiento', $pedido->token_seguimiento);
    }

    /**
     * Pedido al que corresponde un token llegado en la dirección. Además de
     * buscarlo, vuelve a firmar su código y su hora y compara en tiempo
     * constante: un token que no sale de la clave de la aplicación no abre
     * nada, aunque estuviera escrito en la base de datos.
     *
     * @param  string  $token  token de la dirección
     * @return Pedido|null null si el token no es válido
     */
    public function pedidoDe(string $token): ?Pedido
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        $pedido = Pedido::where('token_seguimiento', $token)->first();

        if (! $pedido || ! hash_equals($this->calcular($pedido->codigo, $pedido->creado_en), $token)) {
            return null;
        }

        return $pedido;
    }
}
