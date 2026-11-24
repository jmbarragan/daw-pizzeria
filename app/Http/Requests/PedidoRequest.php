<?php

/**
 * Validación del formulario de datos del pedido de un invitado (RF-04, pantalla P-04).
 */

namespace App\Http\Requests;

use App\Validacion\ReglasDeDatos;
use App\Validacion\Telefono;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Nombre, teléfono, correo, observaciones y aceptación de la política de
 * privacidad. El teléfono se puede escribir como se dice en España, con nueve
 * cifras y espacios, puntos o guiones; antes de validarlo se pasa a E.164
 * (+34…), que es como se guarda. Un número de otro país se escribe con su «+».
 */
class PedidoRequest extends FormRequest
{
    /**
     * Cualquiera puede pedir sin cuenta.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Deja el teléfono en E.164 y el resto de campos sin espacios sobrantes.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'telefono' => Telefono::normalizar((string) $this->input('telefono')),
            // con la sesión iniciada el correo es siempre el de la cuenta, aunque se cambie en el formulario
            'email' => $this->user('cliente')->email ?? trim((string) $this->input('email')),
            'observaciones' => trim((string) $this->input('observaciones')),
        ]);
    }

    /**
     * Reglas de cada campo.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(80)],
            'telefono' => ['required', ...ReglasDeDatos::telefono()],
            'email' => ['required', ...ReglasDeDatos::correo()],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'privacidad' => ['accepted'],
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
            'telefono.regex' => 'Indica un teléfono de 9 cifras, o con el prefijo de tu país (+…) si no es español.',
            'email.email' => 'Revisa el correo: ahí te enviaremos el enlace para seguir el pedido.',
            'privacidad.accepted' => 'Para enviar el pedido tienes que aceptar la política de privacidad.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'telefono' => 'teléfono', 'email' => 'correo electrónico', 'observaciones' => 'observaciones'];
    }

    /**
     * Datos del cliente listos para guardar.
     *
     * @return array{nombre: string, telefono: string, email: string}
     */
    public function cliente(): array
    {
        return [
            'nombre' => (string) $this->input('nombre'),
            'telefono' => (string) $this->input('telefono'),
            'email' => (string) $this->input('email'),
        ];
    }

    /**
     * Observaciones, o null si se han dejado en blanco.
     *
     * @return string|null
     */
    public function observaciones(): ?string
    {
        return $this->input('observaciones') === '' ? null : (string) $this->input('observaciones');
    }
}
