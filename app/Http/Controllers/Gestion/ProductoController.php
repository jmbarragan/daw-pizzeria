<?php

/**
 * Gestión de los productos de la carta (RF-16, CU-14, pantallas G-04 y G-05).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\ProductoRequest;
use App\Models\Alergeno;
use App\Models\Categoria;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Listado con búsqueda por nombre y filtro por categoría, alta, modificación
 * con sus alérgenos, borrado y vuelta a la carta. Un producto con ventas no
 * se borra: se retira, para que los pedidos que lo incluyen sigan cuadrando
 * (CU-14, 6a); si se arrepienten, vuelve con «Volver a la carta».
 */
class ProductoController extends Controller
{
    /**
     * Código de MySQL cuando una clave externa impide borrar una fila.
     *
     * @var int
     */
    private const FILA_CON_HIJOS = 1451;

    /**
     * Productos por categoría y nombre, 15 por página (RNF-03). La búsqueda y
     * el filtro se conservan al pasar de página.
     *
     * @param  Request  $peticion  petición con «buscar» y «categoria» opcionales
     * @return View
     */
    public function listado(Request $peticion): View
    {
        $buscar = trim((string) $peticion->query('buscar', ''));
        $categoria = (int) $peticion->query('categoria', 0);

        $productos = Producto::with('categoria')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->select('productos.*')
            ->when($buscar !== '', fn (Builder $consulta) => $consulta->where('productos.nombre', 'like', '%'.$this->literal($buscar).'%'))
            ->when($categoria > 0, fn (Builder $consulta) => $consulta->where('productos.categoria_id', $categoria))
            ->orderBy('categorias.orden')
            ->orderBy('productos.nombre')
            ->paginate(15)
            ->withQueryString();

        return view('gestion.carta.productos', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('orden')->get(),
            'buscar' => $buscar,
            'categoria' => $categoria,
        ]);
    }

    /**
     * Formulario de alta, con el producto disponible.
     *
     * @return View
     */
    public function nuevo(): View
    {
        return $this->formulario(new Producto(['disponible' => true]));
    }

    /**
     * Guarda un producto nuevo con sus alérgenos.
     *
     * @param  ProductoRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(ProductoRequest $peticion): RedirectResponse
    {
        $producto = DB::transaction(function () use ($peticion) {
            $producto = Producto::create($peticion->datos());
            $producto->alergenos()->sync($peticion->alergenos());

            return $producto;
        });

        return redirect()->route('gestion.carta')->with('estado', "Producto «{$producto->nombre}» añadido a la carta.");
    }

    /**
     * Formulario de modificación (G-05).
     *
     * @param  Producto  $producto  producto de la dirección
     * @return View
     */
    public function editar(Producto $producto): View
    {
        return $this->formulario($producto);
    }

    /**
     * Guarda los cambios de un producto y deja marcados solo los alérgenos
     * que llegan en el formulario.
     *
     * @param  ProductoRequest  $peticion  datos validados
     * @param  Producto  $producto  producto de la dirección
     * @return RedirectResponse
     */
    public function actualizar(ProductoRequest $peticion, Producto $producto): RedirectResponse
    {
        DB::transaction(function () use ($peticion, $producto) {
            $producto->update($peticion->datos());
            $producto->alergenos()->sync($peticion->alergenos());
        });

        return redirect()->route('gestion.carta')->with('estado', "Producto «{$producto->nombre}» guardado.");
    }

    /**
     * Borra un producto sin ventas, con sus alérgenos (la tabla intermedia se
     * borra en cascada). Si tiene ventas, lo retira de la carta.
     *
     * @param  Producto  $producto  producto de la dirección
     * @return RedirectResponse
     *
     * @throws QueryException si MySQL falla por otra causa
     */
    public function borrar(Producto $producto): RedirectResponse
    {
        if (! $producto->lineas()->exists()) {
            try {
                $producto->delete();

                return redirect()->route('gestion.carta')->with('estado', "Producto «{$producto->nombre}» borrado.");
            } catch (QueryException $error) {
                if (($error->errorInfo[1] ?? null) !== self::FILA_CON_HIJOS) {
                    throw $error;
                }
            }
        }

        $producto->update(['retirado_en' => now()]);

        return redirect()->route('gestion.carta')->with('estado',
            "«{$producto->nombre}» ya se ha vendido, así que se ha retirado de la carta en lugar de borrarse. "
            .'Los pedidos antiguos lo conservan, y puedes recuperarlo con «Volver a la carta».');
    }

    /**
     * Devuelve a la carta un producto retirado.
     *
     * @param  Producto  $producto  producto de la dirección
     * @return RedirectResponse
     */
    public function volver(Producto $producto): RedirectResponse
    {
        $producto->update(['retirado_en' => null]);

        return redirect()->route('gestion.carta')->with('estado', "«{$producto->nombre}» vuelve a estar en la carta.");
    }

    /**
     * Vista del formulario con las listas de categorías y alérgenos.
     *
     * @param  Producto  $producto  producto nuevo o existente
     * @return View
     */
    private function formulario(Producto $producto): View
    {
        return view('gestion.carta.producto', [
            'producto' => $producto,
            'categorias' => Categoria::orderBy('orden')->get(),
            'alergenos' => Alergeno::orderBy('nombre')->get(),
            'marcados' => $producto->exists ? $producto->alergenos()->pluck('alergenos.id')->all() : [],
        ]);
    }

    /**
     * Escapa % y _ para que la búsqueda los tome como letras y no como comodines.
     *
     * @param  string  $texto  lo que ha escrito la persona
     * @return string
     */
    private function literal(string $texto): string
    {
        return addcslashes($texto, '\\%_');
    }
}
