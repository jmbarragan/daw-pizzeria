<?php

/**
 * Envío del pedido como invitado (RF-04, CU-03, pantallas P-04 y P-05).
 */

namespace App\Http\Controllers;

use App\Http\Requests\PedidoRequest;
use App\Models\Pedido;
use App\Models\Producto;
use App\Servicios\AjustesPublicos;
use App\Servicios\Calendario;
use App\Servicios\Cesta;
use App\Servicios\EnlaceDeSeguimiento;
use App\Servicios\RegistroDePedidos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * El formulario apunta los precios que se están enseñando. Al confirmar, la
 * cesta se vuelve a leer de la carta: si algo se ha agotado, se ha retirado o
 * ha cambiado de precio, se avisa, se actualiza la cesta y se vuelve al
 * formulario para confirmar con los datos nuevos (CU-03, 6a). Si todo cuadra,
 * se guarda el pedido y se vacía la cesta.
 */
class PedidoController extends Controller
{
    /**
     * Pedidos guardados que admite un mismo correo en una hora (decisión 031).
     *
     * @var int
     */
    const PEDIDOS_POR_CORREO = 10;

    /**
     * Aviso cuando se pasa el límite por IP o por correo.
     *
     * @var string
     */
    const AVISO_DE_LIMITE = 'Has enviado varios pedidos seguidos. Espera un poco o llámanos para hacer el pedido.';

    /**
     * Prepara el controlador con la cesta y el calendario.
     *
     * @param  Cesta  $cesta  contenido y reglas de la cesta
     * @param  Calendario  $calendario  fecha prevista y aviso de abierto o cerrado
     */
    public function __construct(
        private readonly Cesta $cesta,
        private readonly Calendario $calendario,
    ) {}

    /**
     * Formulario de datos con el resumen del pedido.
     *
     * @param  Request  $peticion  petición en curso
     * @param  AjustesPublicos  $ajustes  teléfono del local
     * @return View|RedirectResponse
     */
    public function formulario(Request $peticion, AjustesPublicos $ajustes): View|RedirectResponse
    {
        $lineas = $this->cesta->lineas();
        if ($motivo = $this->porQueNoSePuedeEnviar($lineas)) {
            return redirect()->route('cesta')->with('error', $motivo);
        }

        // precios que ve el cliente, para saber al confirmar si han cambiado
        $peticion->session()->put('precios_vistos', $lineas->mapWithKeys(fn (array $linea) => [
            $linea['producto']->id => (string) $linea['producto']->precio,
        ])->all());

        return view('pedido', [
            'lineas' => $lineas,
            'total' => $this->cesta->total($lineas),
            'alergenos' => $lineas->flatMap(fn (array $linea) => $linea['producto']->alergenos->pluck('nombre'))->unique()->sort()->values(),
            'abierto' => $this->calendario->turnoAbierto(),
            'siguiente' => $this->calendario->siguienteApertura(),
            'telefono' => $ajustes->valor('telefono_local'),
        ]);
    }

    /**
     * Comprueba otra vez la cesta y, si nada ha cambiado, guarda el pedido.
     *
     * @param  PedidoRequest  $peticion  datos validados del cliente
     * @param  RegistroDePedidos  $registro  guardado en una transacción
     * @return RedirectResponse
     */
    public function confirmar(PedidoRequest $peticion, RegistroDePedidos $registro): RedirectResponse
    {
        $vistos = $peticion->session()->get('precios_vistos', []);
        $lineas = $this->cesta->lineas();

        if ($motivo = $this->porQueNoSePuedeEnviar($lineas)) {
            return redirect()->route('cesta')->with('error', $motivo);
        }

        $cambios = $this->cambiosDesde($vistos, $lineas);
        if ($cambios !== []) {
            return redirect()->route('pedido')->withInput()
                ->with('error', 'Antes de enviarlo ha cambiado algo de tu pedido: '.implode('; ', $cambios).'. Revísalo y confírmalo de nuevo.');
        }

        // solo cuentan los pedidos que se guardan: con envíos fallidos nadie puede
        // gastar el cupo del correo de otra persona
        $cupo = 'pedidos-correo:'.hash('sha256', mb_strtolower($peticion->cliente()['email']));
        if (RateLimiter::tooManyAttempts($cupo, self::PEDIDOS_POR_CORREO)) {
            return redirect()->route('pedido')->withInput()->with('error', self::AVISO_DE_LIMITE);
        }

        $pedido = $registro->registrar(
            $peticion->cliente(), $lineas, $peticion->observaciones(), $this->calendario->fechaPrevista()
        );
        RateLimiter::hit($cupo, 3600);

        $peticion->session()->forget(['cesta', 'precios_vistos']);
        $peticion->session()->put('pedido_recibido', $pedido->id);

        return redirect()->route('pedido.recibido');
    }

    /**
     * Confirmación del pedido recién enviado en esta sesión.
     *
     * @param  Request  $peticion  petición en curso
     * @param  EnlaceDeSeguimiento  $enlaces  dirección del seguimiento
     * @return View|RedirectResponse
     */
    public function recibido(Request $peticion, EnlaceDeSeguimiento $enlaces): View|RedirectResponse
    {
        $pedido = Pedido::with('cliente')->find($peticion->session()->get('pedido_recibido'));
        if (! $pedido) {
            return redirect()->route('carta');
        }

        return view('pedido-recibido', ['pedido' => $pedido, 'enlace' => $enlaces->direccion($pedido)]);
    }

    /**
     * Motivo por el que la cesta no se puede enviar, o null si se puede.
     *
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas de la cesta
     * @return string|null
     */
    private function porQueNoSePuedeEnviar(Collection $lineas): ?string
    {
        if ($lineas->isEmpty()) {
            return 'Tu cesta está vacía.';
        }
        if ($lineas->contains(fn (array $linea) => ! $linea['producto']->disponible)) {
            return 'Hay productos agotados en tu cesta: quítalos para poder enviar el pedido.';
        }
        if ($this->cesta->pizzasCon() > $this->cesta->maximoDePizzas()) {
            return 'Por la web se pueden pedir como mucho '.$this->cesta->maximoDePizzas().' pizzas.';
        }
        if ($this->calendario->fechaPrevista() === null) {
            return 'Ahora no tenemos fechas de apertura previstas, así que no podemos recibir pedidos por la web.';
        }

        return null;
    }

    /**
     * Diferencias entre los precios que vio el cliente y los de ahora.
     *
     * @param  array<int, string>  $vistos  id => precio enseñado en el formulario
     * @param  Collection<int, array{producto: Producto, unidades: int, importe: float}>  $lineas  líneas de ahora
     * @return list<string> una frase por cambio
     */
    private function cambiosDesde(array $vistos, Collection $lineas): array
    {
        $cambios = [];
        $ahora = $lineas->keyBy(fn (array $linea) => $linea['producto']->id);

        foreach ($vistos as $id => $precio) {
            if (! $ahora->has($id)) {
                $cambios[] = 'un producto ya no está en la carta';
            }
        }
        foreach ($ahora as $id => $linea) {
            $nuevo = (string) $linea['producto']->precio;
            if (! array_key_exists($id, $vistos)) {
                $cambios[] = "«{$linea['producto']->nombre}» se ha añadido después";
            } elseif ($vistos[$id] !== $nuevo) {
                $cambios[] = sprintf('«%s» ahora cuesta %s €', $linea['producto']->nombre, number_format((float) $nuevo, 2, ',', '.'));
            }
        }

        return array_values(array_unique($cambios));
    }
}
