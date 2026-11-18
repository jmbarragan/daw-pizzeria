<?php

/**
 * Validación del formulario del catálogo de alérgenos (RF-17).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Alergeno;
use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre único de 2 a 40 caracteres y la descripción que ve el cliente en la
 * carta, obligatoria y de hasta 255: es la explicación que pide el Reglamento
 * (UE) 1169/2011, así que no puede quedar vacía.
 */
class AlergenoRequest extends FormRequest
{
    /**
     * Ya ha pasado por «auth:personal»: cualquier persona del personal mantiene el catálogo.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas del nombre y de la descripción.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $alergeno = $this->route('alergeno');

        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(40),
                Rule::unique('alergenos', 'nombre')->ignore($alergeno instanceof Alergeno ? $alergeno->id : null)],
            'descripcion' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    /**
     * Mensajes con las palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ese alérgeno ya está en el catálogo.',
            'descripcion.required' => 'Escribe la explicación que verá el cliente en la carta.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'descripcion' => 'descripción'];
    }

    /**
     * Datos listos para guardar, sin espacios sobrantes.
     *
     * @return array{nombre: string, descripcion: string}
     */
    public function datos(): array
    {
        return [
            'nombre' => trim((string) $this->input('nombre')),
            'descripcion' => trim((string) $this->input('descripcion')),
        ];
    }
}
