<?php

/**
 * Gestión de las categorías de la carta (RF-15, CU-14, pestaña Categorías de G-04).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\CategoriaRequest;
use App\Models\Categoria;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Listado por orden de la carta, alta, modificación y borrado. Una categoría
 * con productos no se borra (CU-14, 7a): se explica el motivo y se propone
 * ocultarla, que deja los productos como están.
 */
class CategoriaController extends Controller
{
    /**
     * Código de MySQL cuando una clave externa impide borrar una fila.
     *
     * @var int
     */
    private const FILA_CON_HIJOS = 1451;

    /**
     * Categorías en el orden en que salen en la carta, con cuántos productos tienen.
     *
     * @return View
     */
    public function listado(): View
    {
        return view('gestion.carta.categorias', [
            'categorias' => Categoria::withCount('productos')->orderBy('orden')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Formulario de alta, con la categoría al final de la carta y visible.
     *
     * @return View
     */
    public function nueva(): View
    {
        $ultima = (int) Categoria::max('orden');

        return view('gestion.carta.categoria', [
            'categoria' => new Categoria(['orden' => min($ultima + 1, 255), 'activa' => true]),
        ]);
    }

    /**
     * Guarda una categoría nueva.
     *
     * @param  CategoriaRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(CategoriaRequest $peticion): RedirectResponse
    {
        $categoria = Categoria::create($peticion->datos());

        return redirect()->route('gestion.carta.categorias')
            ->with('estado', "Categoría «{$categoria->nombre}» creada.");
    }

    /**
     * Formulario de modificación.
     *
     * @param  Categoria  $categoria  categoría de la dirección
     * @return View
     */
    public function editar(Categoria $categoria): View
    {
        return view('gestion.carta.categoria', ['categoria' => $categoria]);
    }

    /**
     * Guarda los cambios de una categoría.
     *
     * @param  CategoriaRequest  $peticion  datos validados
     * @param  Categoria  $categoria  categoría de la dirección
     * @return RedirectResponse
     */
    public function actualizar(CategoriaRequest $peticion, Categoria $categoria): RedirectResponse
    {
        $categoria->update($peticion->datos());

        return redirect()->route('gestion.carta.categorias')
            ->with('estado', "Categoría «{$categoria->nombre}» guardada.");
    }

    /**
     * Borra una categoría vacía. Si tiene productos, vuelve al listado con el
     * motivo; la clave externa de productos lo impediría igualmente, y su error
     * se convierte en el mismo mensaje por si alguien añade un producto a la vez.
     *
     * @param  Categoria  $categoria  categoría de la dirección
     * @return RedirectResponse
     *
     * @throws QueryException si MySQL falla por otra causa
     */
    public function borrar(Categoria $categoria): RedirectResponse
    {
        $productos = $categoria->productos()->count();

        try {
            if ($productos === 0) {
                $categoria->delete();

                return redirect()->route('gestion.carta.categorias')
                    ->with('estado', "Categoría «{$categoria->nombre}» borrada.");
            }
        } catch (QueryException $error) {
            if (($error->errorInfo[1] ?? null) !== self::FILA_CON_HIJOS) {
                throw $error;
            }
            $productos = max(1, $categoria->productos()->count());
        }

        $texto = $productos === 1 ? 'un producto' : "{$productos} productos";

        return redirect()->route('gestion.carta.categorias')->with('error',
            "No se puede borrar «{$categoria->nombre}» porque tiene {$texto}, también los retirados. "
            .'Si no quieres que se vea en la carta, edítala y desmarca «Visible en la carta».');
    }
}
