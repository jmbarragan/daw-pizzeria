<?php

/**
 * Validación de la contraseña nueva del personal.
 */

namespace App\Http\Requests\Gestion;

use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pide la contraseña dos veces, con al menos diez caracteres, letras y números.
 */
class NuevaContrasenaRequest extends FormRequest
{
    /**
     * Quien llega con el enlace puede enviar el formulario; el token se
     * comprueba al guardar.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Token del enlace, correo y contraseña confirmada.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'contrasena' => ['required', 'confirmed', ...ReglasDeDatos::contrasena()],
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
