<?php

/**
 * Validación de los ajustes del local que edita el administrador (RF-21, pantalla G-10).
 */

namespace App\Http\Requests\Gestion;

use App\Validacion\ReglasDeDatos;
use App\Validacion\Telefono;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Teléfono, dirección y política de privacidad del local, y el servidor de
 * correo saliente. La contraseña del correo es opcional: vacía, se queda la
 * que había; escrita, se guarda cifrada y nunca se vuelve a mostrar.
 */
class AjustesRequest extends FormRequest
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
     * Teléfono en formato E.164 y textos sin espacios sobrantes antes de validar.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'telefono_local' => Telefono::normalizar((string) $this->input('telefono_local')),
            'direccion_local' => trim((string) $this->input('direccion_local')),
            'politica_privacidad' => trim((string) $this->input('politica_privacidad')),
            'smtp_servidor' => mb_strtolower(trim((string) $this->input('smtp_servidor'))),
            'smtp_usuario' => trim((string) $this->input('smtp_usuario')),
        ]);
    }

    /**
     * Reglas de cada ajuste; el servidor es un nombre de máquina, sin protocolo ni barras.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'telefono_local' => ['required', ...ReglasDeDatos::telefono()],
            'direccion_local' => ['required', 'string', 'min:5', 'max:150'],
            'politica_privacidad' => ['required', 'string', 'min:50', 'max:5000'],
            'smtp_servidor' => ['required', ...ReglasDeDatos::servidorDeCorreo()],
            'smtp_puerto' => ['required', 'integer', 'between:1,65535'],
            'smtp_usuario' => ['required', 'string', 'max:254'],
            'smtp_contrasena' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Mensajes con palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'telefono_local.regex' => 'Indica un teléfono de 9 cifras, o con el prefijo del país (+…).',
            'smtp_servidor.regex' => 'Escribe solo el nombre del servidor, como smtp.gmail.com, sin «https://» ni barras.',
            'politica_privacidad.min' => 'La política de privacidad tiene que decir al menos quién es el responsable y para qué se usan los datos.',
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
            'telefono_local' => 'teléfono', 'direccion_local' => 'dirección', 'politica_privacidad' => 'política de privacidad',
            'smtp_servidor' => 'servidor', 'smtp_puerto' => 'puerto', 'smtp_usuario' => 'usuario', 'smtp_contrasena' => 'contraseña',
        ];
    }
}
