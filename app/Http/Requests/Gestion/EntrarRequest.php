<?php

/**
 * Validación del formulario de acceso del personal (G-01).
 */

namespace App\Http\Requests\Gestion;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo comprueba que llegan los dos datos; si son correctos lo decide el
 * controlador, con un mensaje que no distingue entre correo y contraseña.
 */
class EntrarRequest extends FormRequest
{
    /**
     * Cualquiera puede intentar entrar.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Correo y contraseña obligatorios.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'contrasena' => ['required', 'string'],
        ];
    }

    /**
     * Nombres de los campos en los mensajes de error.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'correo electrónico',
            'contrasena' => 'contraseña',
        ];
    }
}
