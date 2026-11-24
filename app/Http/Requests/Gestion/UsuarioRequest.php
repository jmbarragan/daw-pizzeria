<?php

/**
 * Validación del formulario de usuarios del personal (RF-21, CU-20).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Usuario;
use App\Validacion\ReglasDeDatos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre, correo de acceso y rol. La contraseña no está en el formulario: la
 * elige el propio empleado con el enlace del alta (decisión 032). El correo
 * no se puede repetir entre el personal (CU-20, 3a).
 */
class UsuarioRequest extends FormRequest
{
    /**
     * Ya ha pasado por «rol:administrador».
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Textos sin espacios sobrantes y el correo en minúsculas antes de validar.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'email' => mb_strtolower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * Reglas de los tres campos; en la modificación, el correo puede seguir siendo el suyo.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(80)],
            'email' => ['required', ...ReglasDeDatos::correo(),
                Rule::unique('usuarios', 'email')->ignore($usuario instanceof Usuario ? $usuario->id : null)],
            'rol' => ['required', Rule::in([Usuario::ROL_EMPLEADO, Usuario::ROL_ADMINISTRADOR])],
        ];
    }

    /**
     * Mensajes con palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['email.unique' => 'Ese correo ya es de otra persona del personal.'];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'correo electrónico'];
    }

    /**
     * Datos listos para guardar.
     *
     * @return array{nombre: string, email: string, rol: string}
     */
    public function datos(): array
    {
        return [
            'nombre' => (string) $this->input('nombre'),
            'email' => (string) $this->input('email'),
            'rol' => (string) $this->input('rol'),
        ];
    }
}
