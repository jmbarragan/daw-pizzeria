<?php

/**
 * Validación del formulario de clientes de la gestión (RF-18).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Cliente;
use App\Validacion\ReglasDeDatos;
use App\Validacion\Telefono;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Nombre, teléfono y correo opcional, con las mismas reglas que el pedido. El
 * personal no toca la contraseña: la cuenta la crea el propio cliente. Dos
 * invitados pueden compartir correo, pero el de un cliente con
 * cuenta no puede ser el de otra cuenta.
 */
class ClienteRequest extends FormRequest
{
    /**
     * Ya ha pasado por «auth:personal»: cualquier persona del personal gestiona los clientes.
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
            'nombre' => trim((string) $this->input('nombre')),
            'telefono' => Telefono::normalizar((string) $this->input('telefono')),
            'email' => trim((string) $this->input('email')),
        ]);
    }

    /**
     * Reglas de los tres campos. Solo si el cliente tiene cuenta se exige que
     * su correo no sea el de otra cuenta.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $cliente = $this->route('cliente');
        $correo = ['nullable', ...ReglasDeDatos::correo()];

        if ($cliente instanceof Cliente && ! $cliente->esInvitado()) {
            $correo[] = Rule::unique('clientes', 'email')->whereNotNull('contrasena')->ignore($cliente->id);
        }

        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(80)],
            'telefono' => ['required', ...ReglasDeDatos::telefono()],
            'email' => $correo,
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
            'telefono.regex' => 'Indica un teléfono de 9 cifras, o con el prefijo de su país (+…) si no es español.',
            'email.unique' => 'Otra cuenta de cliente ya usa ese correo.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'telefono' => 'teléfono', 'email' => 'correo electrónico'];
    }

    /**
     * Datos listos para guardar; un correo vacío se guarda como NULL.
     *
     * @return array{nombre: string, telefono: string, email: string|null}
     */
    public function datos(): array
    {
        $correo = (string) $this->input('email');

        return [
            'nombre' => (string) $this->input('nombre'),
            'telefono' => (string) $this->input('telefono'),
            'email' => $correo === '' ? null : $correo,
        ];
    }
}
