<?php

/**
 * Panel, detalle y pedido telefónico en la gestión (RF-11 a RF-14, pantallas G-02 y G-03).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\PedidoTelefonicoRequest;
use App\Models\Categoria;
use App\Models\Pedido;
use App\Servicios\Calendario;
use App\Servicios\CambiosDeEstado;
use App\Servicios\RegistroDePedidos;
use App\Validacion\Telefono;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tres vistas del mismo listado. «Pendientes», del más antiguo al más nuevo,
 * para atenderlos por orden de llegada. «Hoy», todos los que se recogen hoy,
 * por hora. Y la búsqueda por código, teléfono, fecha prevista o estado, de
 * los más nuevos a los más antiguos. El total sale de v_pedidos_totales, sin
 * calcularlo pedido a pedido.
 */
class PedidoController extends Controller
{
    /**
     * Listado del panel según la pestaña y la búsqueda.
     *
     * @param  Request  $peticion  petición con «vista» y los campos de búsqueda
     * @return View
     */
    public function panel(Request $peticion): View
    {
        $filtros = [
            'codigo' => strtoupper(trim((string) $peticion->query('codigo', ''))),
            'telefono' => trim((string) $peticion->query('telefono', '')),
            'fecha' => (string) $peticion->query('fecha', ''),
            'estado' => (string) $peticion->query('estado', ''),
        ];
        $buscando = array_filter($filtros) !== [];
        $vista = $buscando ? 'buscar' : ($peticion->query('vista') === 'hoy' ? 'hoy' : 'pendientes');

        $consulta = Pedido::query()
            ->join('v_pedidos_totales as totales', 'totales.pedido_id', '=', 'pedidos.id')
            ->join('clientes', 'clientes.id', '=', 'pedidos.cliente_id')
            ->select('pedidos.*', 'totales.total as importe', 'clientes.nombre as cliente_nombre');

        match ($vista) {
            'pendientes' => $consulta->where('pedidos.estado', 'pendiente')->orderBy('pedidos.creado_en'),
            'hoy' => $consulta->whereDate('pedidos.fecha_prevista', today())
                ->orderByRaw('pedidos.hora_recogida IS NULL, pedidos.hora_recogida')->orderBy('pedidos.creado_en'),
            'buscar' => $this->filtrar($consulta, $filtros)->orderByDesc('pedidos.creado_en'),
        };

        return view('gestion.pedidos', [
            'pedidos' => $consulta->paginate(15)->withQueryString(),
            'vista' => $vista,
            'filtros' => $filtros,
            'pendientes' => Pedido::where('estado', 'pendiente')->count(),
        ]);
    }

    /**
     * Detalle de un pedido (G-03) con lo necesario para decidirlo.
     *
     * @param  Pedido  $pedido  pedido de la dirección, por su código
     * @param  Calendario  $calendario  franjas de recogida del día previsto
     * @return View
     */
    public function detalle(Pedido $pedido, Calendario $calendario): View
    {
        $pedido->load(['cliente', 'usuario', 'lineas.producto.alergenos']);

        return view('gestion.pedido', [
            'pedido' => $pedido,
            'franjas' => $pedido->estado === 'pendiente' ? array_keys($calendario->franjas($pedido->fecha_prevista)) : [],
            'alergenos' => $pedido->lineas->flatMap(fn ($linea) => $linea->producto->alergenos->pluck('nombre'))->unique()->sort()->values(),
        ]);
    }

    /**
     * Acepta el pedido con una de las franjas del día previsto.
     *
     * @param  Request  $peticion  petición con «hora»
     * @param  Pedido  $pedido  pedido de la dirección
     * @param  Calendario  $calendario  franjas válidas
     * @param  CambiosDeEstado  $cambios  cambio condicionado a que siga pendiente
     * @return RedirectResponse
     */
    public function aceptar(Request $peticion, Pedido $pedido, Calendario $calendario, CambiosDeEstado $cambios): RedirectResponse
    {
        $franjas = $calendario->franjas($pedido->fecha_prevista);
        $peticion->validate(
            ['hora' => ['required', Rule::in(array_keys($franjas))]],
            ['hora.required' => 'Elige la hora de recogida.', 'hora.in' => 'Esa hora ya no está disponible: elige otra de la lista.']
        );

        $hora = (string) $peticion->input('hora');
        if (! $cambios->aceptar($pedido, $peticion->user('personal'), $hora, $franjas[$hora])) {
            return $this->llegaTarde($pedido);
        }

        return redirect()->route('gestion.pedidos.detalle', $pedido)
            ->with('estado', "Pedido {$pedido->codigo} aceptado para recoger a las {$hora}.");
    }

    /**
     * Rechaza el pedido con su motivo.
     *
     * @param  Request  $peticion  petición con «motivo»
     * @param  Pedido  $pedido  pedido de la dirección
     * @param  CambiosDeEstado  $cambios  cambio condicionado a que siga pendiente
     * @return RedirectResponse
     */
    public function rechazar(Request $peticion, Pedido $pedido, CambiosDeEstado $cambios): RedirectResponse
    {
        $peticion->validate(
            ['motivo' => ['required', 'string', 'min:3', 'max:255']],
            ['motivo.required' => 'Escribe el motivo: el cliente lo recibirá por correo.']
        );

        if (! $cambios->rechazar($pedido, $peticion->user('personal'), trim((string) $peticion->input('motivo')))) {
            return $this->llegaTarde($pedido);
        }

        return redirect()->route('gestion.pedidos.detalle', $pedido)->with('estado', "Pedido {$pedido->codigo} rechazado.");
    }

    /**
     * Lleva el pedido al estado siguiente: listo, recogido y pagado, o no
     * recogido. Una transición que no toca, aunque se fuerce, se rechaza con
     * su motivo (CU-12, 2a).
     *
     * @param  Request  $peticion  petición con «estado»
     * @param  Pedido  $pedido  pedido de la dirección
     * @param  CambiosDeEstado  $cambios  transiciones permitidas y condicionadas
     * @return RedirectResponse
     */
    public function avanzar(Request $peticion, Pedido $pedido, CambiosDeEstado $cambios): RedirectResponse
    {
        $antes = $pedido->estado;
        $estado = (string) $peticion->input('estado');

        if (! $cambios->avanzar($pedido, $estado)) {
            $ahora = $pedido->refresh()->estado;
            $aviso = $ahora === $antes
                ? 'No se puede pasar de «'.Pedido::ETIQUETAS[$antes].'» a «'.(Pedido::ETIQUETAS[$estado] ?? $estado).'».'
                : "No se ha aplicado: el pedido {$pedido->codigo} ya está «".Pedido::ETIQUETAS[$ahora].'».';

            return redirect()->route('gestion.pedidos.detalle', $pedido)->with('error', $aviso);
        }

        $avisos = [
            'listo' => "Pedido {$pedido->codigo} listo para recoger.",
            'recogido' => "Pedido {$pedido->codigo} recogido; el pago queda apuntado.",
            'no_recogido' => "Pedido {$pedido->codigo} cerrado como no recogido.",
        ];

        return redirect()->route('gestion.pedidos.detalle', $pedido)->with('estado', $avisos[$estado]);
    }

    /**
     * Formulario del pedido telefónico con los productos que se pueden pedir y
     * las franjas del próximo día con huecos.
     *
     * @param  Calendario  $calendario  día y franjas de recogida
     * @return View
     */
    public function telefonico(Calendario $calendario): View
    {
        $dia = $this->diaConFranjas($calendario);

        return view('gestion.pedido-telefonico', [
            'categorias' => Categoria::where('activa', true)->orderBy('orden')->orderBy('nombre')
                ->with(['productos' => fn ($productos) => $productos->enCarta()->where('disponible', true)->orderBy('nombre')])
                ->get()
                ->filter(fn (Categoria $categoria) => $categoria->productos->isNotEmpty()),
            'dia' => $dia,
            'franjas' => $dia ? array_keys($calendario->franjas($dia)) : [],
        ]);
    }

    /**
     * Guarda el pedido telefónico, ya aceptado, con su hora de recogida.
     *
     * @param  PedidoTelefonicoRequest  $peticion  datos validados del cliente y las unidades
     * @param  Calendario  $calendario  franjas válidas
     * @param  RegistroDePedidos  $registro  guardado en una transacción, con código y token
     * @return RedirectResponse
     */
    public function guardarTelefonico(PedidoTelefonicoRequest $peticion, Calendario $calendario, RegistroDePedidos $registro): RedirectResponse
    {
        $dia = $this->diaConFranjas($calendario);
        $franjas = $dia ? $calendario->franjas($dia) : [];
        $hora = (string) $peticion->input('hora');

        if (! isset($franjas[$hora])) {
            return back()->withInput()->withErrors(['hora' => 'Esa hora ya no está disponible: elige otra de la lista.']);
        }

        $pedido = $registro->registrarTelefonico(
            $peticion->cliente(), $peticion->lineas(), $peticion->observaciones(), $dia,
            $peticion->user('personal'), $hora, $franjas[$hora]
        );

        return redirect()->route('gestion.pedidos.detalle', $pedido)
            ->with('estado', "Pedido telefónico {$pedido->codigo} apuntado para recoger el {$dia->format('d/m')} a las {$hora}.");
    }

    /**
     * Primer día, a partir de la fecha prevista de ahora, al que le quedan
     * franjas de recogida; se miran como mucho dos semanas.
     *
     * @param  Calendario  $calendario  fecha prevista y franjas
     * @return CarbonInterface|null null si no hay ninguna franja en ese plazo
     */
    private function diaConFranjas(Calendario $calendario): ?CarbonInterface
    {
        $dia = $calendario->fechaPrevista();

        for ($n = 0; $dia && $n < 14; $n++, $dia = $dia->copy()->addDay()) {
            if ($calendario->franjas($dia) !== []) {
                return $dia;
            }
        }

        return null;
    }

    /**
     * Aviso cuando la decisión no se ha aplicado porque el pedido ya no estaba
     * pendiente (CU-11, 4a).
     *
     * @param  Pedido  $pedido  pedido con su estado de ahora
     * @return RedirectResponse
     */
    private function llegaTarde(Pedido $pedido): RedirectResponse
    {
        $aviso = $pedido->estado === 'cancelado'
            ? "No se ha aplicado: el cliente acaba de cancelar el pedido {$pedido->codigo}."
            : "No se ha aplicado: el pedido {$pedido->codigo} ya está «".Pedido::ETIQUETAS[$pedido->estado].'».';

        return redirect()->route('gestion.pedidos.detalle', $pedido)->with('error', $aviso);
    }

    /**
     * Aplica los criterios de búsqueda que se hayan rellenado.
     *
     * @param  Builder<Pedido>  $consulta  consulta del listado
     * @param  array{codigo: string, telefono: string, fecha: string, estado: string}  $filtros  campos de búsqueda
     * @return Builder<Pedido>
     */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        if ($filtros['codigo'] !== '') {
            // el código se dice en voz alta y a veces solo el principio: vale empezar por él
            $consulta->where('pedidos.codigo', 'like', addcslashes($filtros['codigo'], '\\%_').'%');
        }
        if ($filtros['telefono'] !== '') {
            $cifras = ltrim(Telefono::normalizar($filtros['telefono']), '+');
            $consulta->where('clientes.telefono', 'like', '%'.preg_replace('/\D/', '', $cifras).'%');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtros['fecha'])) {
            $consulta->whereDate('pedidos.fecha_prevista', Carbon::parse($filtros['fecha']));
        }
        if (in_array($filtros['estado'], Pedido::ESTADOS, true)) {
            $consulta->where('pedidos.estado', $filtros['estado']);
        }

        return $consulta;
    }
}
