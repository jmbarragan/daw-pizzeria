<?php

/**
 * Gestión de los clientes (RF-18, CU-15, pantalla G-06).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ClienteRequest;
use App\Models\Cliente;
use App\Validacion\Telefono;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Listado con búsqueda por nombre o teléfono, alta, modificación y borrado.
 * Un cliente sin pedidos se borra. Uno con pedidos se anonimiza: se quitan el
 * teléfono, el correo y la contraseña y se conserva el nombre, que siguen
 * necesitando los pedidos y los informes. Mientras tenga un pedido sin cerrar
 * no se toca, porque su correo hace falta para avisarle.
 */
class ClienteController extends Controller
{
    /**
     * Código de MySQL cuando una clave externa impide borrar una fila.
     *
     * @var int
     */
    private const FILA_CON_HIJOS = 1451;

    /**
     * Estados de un pedido que aún no se ha cerrado.
     *
     * @var list<string>
     */
    private const SIN_CERRAR = ['pendiente', 'aceptado', 'listo'];

    /**
     * Clientes, de los más nuevos a los más antiguos, con cuántos pedidos tienen.
     *
     * @param  Request  $peticion  petición con «buscar», el nombre o el teléfono
     * @return View
     */
    public function listado(Request $peticion): View
    {
        $buscar = trim((string) $peticion->query('buscar', ''));
        $consulta = Cliente::withCount('pedidos')->orderByDesc('creado_en')->orderByDesc('id');

        if ($buscar !== '') {
            $cifras = preg_replace('/\D/', '', ltrim(Telefono::normalizar($buscar), '+'));
            $consulta->where(function ($donde) use ($buscar, $cifras) {
                $donde->where('nombre', 'like', '%'.addcslashes($buscar, '\\%_').'%');
                if (strlen($cifras) >= 3) {
                    $donde->orWhere('telefono', 'like', "%{$cifras}%");
                }
            });
        }

        return view('gestion.clientes', [
            'clientes' => $consulta->paginate(15)->withQueryString(),
            'buscar' => $buscar,
        ]);
    }

    /**
     * Formulario de alta.
     *
     * @return View
     */
    public function nuevo(): View
    {
        return view('gestion.cliente', ['cliente' => new Cliente]);
    }

    /**
     * Guarda un cliente nuevo, sin cuenta.
     *
     * @param  ClienteRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(ClienteRequest $peticion): RedirectResponse
    {
        $cliente = Cliente::create($peticion->datos());

        return redirect()->route('gestion.clientes')->with('estado', "Cliente «{$cliente->nombre}» creado.");
    }

    /**
     * Formulario de modificación; un cliente anonimizado ya no se edita.
     *
     * @param  Cliente  $cliente  cliente de la dirección
     * @return View|RedirectResponse
     */
    public function editar(Cliente $cliente): View|RedirectResponse
    {
        if ($cliente->anonimizado_en) {
            return $this->yaAnonimizado($cliente);
        }

        return view('gestion.cliente', ['cliente' => $cliente]);
    }

    /**
     * Guarda los cambios de un cliente.
     *
     * @param  ClienteRequest  $peticion  datos validados
     * @param  Cliente  $cliente  cliente de la dirección
     * @return RedirectResponse
     */
    public function actualizar(ClienteRequest $peticion, Cliente $cliente): RedirectResponse
    {
        if ($cliente->anonimizado_en) {
            return $this->yaAnonimizado($cliente);
        }

        $cliente->update($peticion->datos());

        return redirect()->route('gestion.clientes')->with('estado', "Cliente «{$cliente->nombre}» guardado.");
    }

    /**
     * Borra el cliente si no tiene pedidos y, si los tiene todos cerrados, lo anonimiza.
     *
     * @param  Cliente  $cliente  cliente de la dirección
     * @return RedirectResponse
     *
     * @throws QueryException si MySQL falla por otra causa que un pedido nuevo
     */
    public function borrar(Cliente $cliente): RedirectResponse
    {
        if ($cliente->anonimizado_en) {
            return $this->yaAnonimizado($cliente);
        }

        if ($cliente->pedidos()->whereIn('estado', self::SIN_CERRAR)->exists()) {
            return redirect()->route('gestion.clientes')->with('error',
                "«{$cliente->nombre}» tiene un pedido sin cerrar: no se puede borrar hasta que se recoja, se rechace o se cancele.");
        }

        if (! $cliente->pedidos()->exists()) {
            try {
                $cliente->delete();

                return redirect()->route('gestion.clientes')->with('estado', "Cliente «{$cliente->nombre}» borrado.");
            } catch (QueryException $error) {
                // un pedido ha llegado entre medias: se anonimiza como cualquier cliente con pedidos
                if (($error->errorInfo[1] ?? null) !== self::FILA_CON_HIJOS) {
                    throw $error;
                }
            }
        }

        $this->anonimizar($cliente);

        return redirect()->route('gestion.clientes')->with('estado',
            "«{$cliente->nombre}» tiene pedidos, así que no se borra: se han quitado su teléfono, su correo y su cuenta.");
    }

    /**
     * Quita los datos de contacto y la cuenta en una sola sentencia, para que
     * los CHECK de la tabla se cumplan en todo momento.
     *
     * @param  Cliente  $cliente  cliente que se anonimiza
     * @return void
     */
    private function anonimizar(Cliente $cliente): void
    {
        DB::table('clientes')->where('id', $cliente->id)->update([
            'telefono' => null,
            'email' => null,
            'contrasena' => null,
            'email_verificado_en' => null,
            'anonimizado_en' => now(),
        ]);
    }

    /**
     * Aviso cuando se intenta tocar un cliente ya anonimizado.
     *
     * @param  Cliente  $cliente  cliente anonimizado
     * @return RedirectResponse
     */
    private function yaAnonimizado(Cliente $cliente): RedirectResponse
    {
        return redirect()->route('gestion.clientes')->with('error',
            "«{$cliente->nombre}» está anonimizado: ya no tiene datos de contacto que cambiar.");
    }
}
