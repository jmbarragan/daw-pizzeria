<?php

/**
 * Validación del formulario de producto (RF-16, pantalla G-05).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Producto;
use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre único de 2 a 60 caracteres, descripción opcional, categoría, precio
 * con IVA, alérgenos declarados y si está disponible o agotado. El precio se
 * puede escribir con coma decimal, como se escriben los importes en España
 * (RNF-12): «9,50» se valida y se guarda como 9.50.
 */
class ProductoRequest extends FormRequest
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
     * Cambia la coma decimal del precio por un punto antes de validar.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('precio'))) {
            $this->merge(['precio' => str_replace(',', '.', trim($this->input('precio')))]);
        }
    }

    /**
     * Reglas de cada campo del formulario.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $producto = $this->route('producto');

        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(60),
                Rule::unique('productos', 'nombre')->ignore($producto instanceof Producto ? $producto->id : null)],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'precio' => ['required', ...ReglasDeDatos::precio()],
            'alergenos' => ['array'],
            'alergenos.*' => ['integer', 'distinct', 'exists:alergenos,id'],
            'disponible' => ['boolean'],
        ];
    }

    /**
     * Mensajes con las palabras de la pantalla. El nombre repetido avisa de
     * que puede ser un producto retirado, que no sale en la carta pero sigue
     * guardando su nombre (decisión 026).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya hay un producto con ese nombre; puede que esté retirado de la carta.',
            'precio.gt' => 'El precio debe ser mayor que 0.',
            'precio.max' => 'El precio no puede pasar de 999,99 €.',
            'precio.decimal' => 'El precio lleva como mucho dos decimales, por ejemplo 9,50.',
            'categoria_id.exists' => 'Elige una de las categorías de la lista.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'categoria_id' => 'categoría',
            'precio' => 'precio',
            'alergenos' => 'alérgenos',
            'alergenos.*' => 'alérgeno',
            'disponible' => 'disponible',
        ];
    }

    /**
     * Datos de la tabla productos, listos para guardar. Una descripción en
     * blanco se guarda como NULL, que es lo que el diseño prevé para las bebidas.
     *
     * @return array{nombre: string, descripcion: string|null, categoria_id: int, precio: string, disponible: bool}
     */
    public function datos(): array
    {
        $descripcion = trim((string) $this->input('descripcion'));

        return [
            'nombre' => trim((string) $this->input('nombre')),
            'descripcion' => $descripcion === '' ? null : $descripcion,
            'categoria_id' => (int) $this->input('categoria_id'),
            'precio' => number_format((float) $this->input('precio'), 2, '.', ''),
            'disponible' => $this->boolean('disponible'),
        ];
    }

    /**
     * Identificadores de los alérgenos marcados.
     *
     * @return list<int>
     */
    public function alergenos(): array
    {
        return array_values(array_map('intval', (array) $this->input('alergenos', [])));
    }
}
