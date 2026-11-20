<?php

/**
 * Carta pública de la pizzería (RF-01, pantalla P-02).
 */

namespace App\Http\Controllers;

use App\Models\Categoria;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

/**
 * Enseña las categorías visibles en su orden de la carta y, a igualdad de
 * orden, por nombre, que es lo que explica la ayuda de la gestión. Dentro de
 * cada una salen sus productos sin retirar, con sus alérgenos; los agotados
 * se ven, pero sin botón para añadirlos. Usa la conexión «web», que solo lee.
 */
class CartaController extends Controller
{
    /**
     * Carta completa, sin las categorías que se quedan vacías.
     *
     * @return View
     */
    public function mostrar(): View
    {
        $categorias = Categoria::where('activa', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->with(['productos' => fn (HasMany $productos) => $productos->enCarta()->orderBy('nombre')->with('alergenos')])
            ->get()
            ->filter(fn (Categoria $categoria) => $categoria->productos->isNotEmpty())
            ->values();

        // los alérgenos que salen en la carta, para explicarlos una sola vez al final
        $alergenos = $categorias->flatMap(fn (Categoria $categoria) => $categoria->productos->flatMap->alergenos)
            ->unique('id')
            ->sortBy('nombre')
            ->values();

        return view('carta', compact('categorias', 'alergenos'));
    }
}
