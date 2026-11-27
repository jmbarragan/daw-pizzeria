<?php

/**
 * Validación del alta de un pedido telefónico (RF-14).
 */

namespace App\Http\Requests\Gestion;

use App\Models\Producto;
use App\Validacion\ReglasDeDatos;
use App\Validacion\Telefono;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

/**
 * Nombre y teléfono del cliente, correo opcional, las unidades
 * de cada producto (de 0 a 20; al menos uno) y la hora de recogida. No hay
 * máximo de pizzas: los pedidos grandes son justo los que se piden por
 * teléfono (RF-03). Los productos tienen que seguir en la carta y sin agotar.
 */
class PedidoTelefonicoRequest extends FormRequest
{
    /**
     * Ya ha pasado por «auth:personal».
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Teléfono en E.164 y unidades sin los productos que se han dejado a cero.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'telefono' => Telefono::normalizar((string) $this->input('telefono')),
            'email' => trim((string) $this->input('email')),
            'observaciones' => trim((string) $this->input('observaciones')),
            'unidades' => array_filter((array) $this->input('unidades', []), fn ($n) => (int) $n > 0),
        ]);
    }

    /**
     * Reglas de cada campo; la hora se comprueba contra las franjas en el controlador.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', ...ReglasDeDatos::nombre(80)],
            'telefono' => ['required', ...ReglasDeDatos::telefono()],
            'email' => ['nullable', ...ReglasDeDatos::correo()],
            'observaciones' => ['nullable', 'string', 'max:255'],
            'unidades' => ['required', 'array'],
            'unidades.*' => ['integer', 'between:1,20'],
            'hora' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * Comprueba que los productos marcados se pueden pedir.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validador) {
            if ($validador->errors()->has('unidades') || $validador->errors()->has('unidades.*')) {
                return;
            }

            $pedidos = array_map('intval', array_keys((array) $this->input('unidades')));
            if ($this->productos()->count() !== count($pedidos)) {
                $validador->errors()->add('unidades', 'Algún producto ya no se puede pedir: está agotado o fuera de la carta.');
            }
        }];
    }

    /**
     * Mensajes con las palabras de la pantalla.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unidades.required' => 'Apunta al menos un producto.',
            'unidades.*.between' => 'Cada producto va de 1 a 20 unidades.',
            'telefono.regex' => 'Apunta un teléfono de 9 cifras, o con el prefijo de su país (+…) si no es español.',
            'hora.required' => 'Elige la hora de recogida.',
        ];
    }

    /**
     * Nombres de los campos en los demás mensajes.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nombre' => 'nombre', 'telefono' => 'teléfono', 'email' => 'correo electrónico', 'hora' => 'hora de recogida'];
    }

    /**
     * Datos del cliente; el correo, null si se ha dejado en blanco.
     *
     * @return array{nombre: string, telefono: string, email: string|null}
     */
    public function cliente(): array
    {
        return [
            'nombre' => (string) $this->input('nombre'),
            'telefono' => (string) $this->input('telefono'),
            'email' => $this->input('email') === '' ? null : (string) $this->input('email'),
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

    /**
     * Líneas del pedido con los precios de la carta de ahora.
     *
     * @return Collection<int, array{producto: Producto, unidades: int, importe: float}>
     */
    public function lineas(): Collection
    {
        $unidades = array_map('intval', (array) $this->input('unidades'));

        return $this->productos()->map(fn (Producto $producto) => [
            'producto' => $producto,
            'unidades' => $unidades[$producto->id],
            'importe' => $unidades[$producto->id] * (float) $producto->precio,
        ]);
    }

    /**
     * Productos marcados que se pueden pedir: en la carta, de una categoría visible y sin agotar.
     *
     * @return Collection<int, Producto>
     */
    private function productos(): Collection
    {
        return Producto::enCarta()
            ->where('disponible', true)
            ->whereHas('categoria', fn ($categoria) => $categoria->where('activa', true))
            ->whereKey(array_map('intval', array_keys((array) $this->input('unidades'))))
            ->orderBy('nombre')
            ->get();
    }
}
