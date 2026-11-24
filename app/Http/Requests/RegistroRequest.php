<?php

/**
 * Validación del registro de un cliente tras su pedido (RF-08, CU-07).
 */

namespace App\Http\Requests;

use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Solo hace falta la contraseña, repetida: el nombre, el teléfono y el correo
 * ya están en el pedido. Se pide lo mismo que al personal: al menos 10
 * caracteres con letras y números.
 */
class RegistroRequest extends FormRequest
{
    /**
     * Lo puede pedir cualquiera; el controlador comprueba que el pedido es de esta sesión.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Contraseña confirmada.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return ['contrasena' => ['required', 'confirmed', ...ReglasDeDatos::contrasena()]];
    }

    /**
     * Nombre del campo en los mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['contrasena' => 'contraseña'];
    }
}
