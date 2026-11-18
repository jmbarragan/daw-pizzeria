<?php

/**
 * Validación del formulario de categorías de la carta (RF-15).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Categoria;
use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre único de 2 a 40 caracteres, la posición en la carta (0 a 255, lo que
 * cabe en la columna) y si la sección se ve o no. Sirve para el alta y para
 * la modificación: en la modificación, el nombre puede seguir siendo el suyo.
 */
class CategoriaRequest extends FormRequest
{
    /**
     * Ya ha pasado por «auth:personal»: cualquier persona del personal gestiona la carta.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de los tres campos.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $categoria = $this->route('categoria');

        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(40),
                Rule::unique('categorias', 'nombre')->ignore($categoria instanceof Categoria ? $categoria->id : null)],
            'orden' => ['required', 'integer', 'between:0,255'],
            'activa' => ['boolean'],
        ];
    }

    /**
     * El nombre repetido se explica con palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya hay una categoría con ese nombre.',
            'orden.between' => 'El orden va de 0 a 255; las categorías con número más bajo salen antes.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'orden' => 'orden', 'activa' => 'visible en la carta'];
    }

    /**
     * Datos listos para guardar: el nombre sin espacios sobrantes y la casilla
     * como verdadero o falso (si no se marca, el navegador no la envía).
     *
     * @return array{nombre: string, orden: int, activa: bool}
     */
    public function datos(): array
    {
        return [
            'nombre' => trim((string) $this->input('nombre')),
            'orden' => (int) $this->input('orden'),
            'activa' => $this->boolean('activa'),
        ];
    }
}
