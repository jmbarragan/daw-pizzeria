<?php

/**
 * Gestión del catálogo de alérgenos (RF-17, CU-14, pestaña Alérgenos de G-04).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\AlergenoRequest;
use App\Models\Alergeno;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * El catálogo llega con los 14 alérgenos del reglamento desde los sembradores;
 * esta pantalla sirve sobre todo para corregir sus textos. Un alérgeno que
 * declara algún producto no se borra (CU-14, 7a): se dice cuáles lo usan.
 */
class AlergenoController extends Controller
{
    /**
     * Código de MySQL cuando una clave externa impide borrar una fila.
     *
     * @var int
     */
    private const FILA_CON_HIJOS = 1451;

    /**
     * Productos que se nombran en el aviso de borrado; si hay más, se cuentan.
     *
     * @var int
     */
    private const PRODUCTOS_EN_EL_AVISO = 3;

    /**
     * Catálogo por orden alfabético, con cuántos productos declara cada alérgeno.
     *
     * @return View
     */
    public function listado(): View
    {
        return view('gestion.carta.alergenos', [
            'alergenos' => Alergeno::withCount('productos')->orderBy('nombre')->paginate(15),
        ]);
    }

    /**
     * Formulario de alta.
     *
     * @return View
     */
    public function nuevo(): View
    {
        return view('gestion.carta.alergeno', ['alergeno' => new Alergeno]);
    }

    /**
     * Guarda un alérgeno nuevo.
     *
     * @param  AlergenoRequest  $peticion  datos validados
     * @return RedirectResponse
     */
    public function guardar(AlergenoRequest $peticion): RedirectResponse
    {
        $alergeno = Alergeno::create($peticion->datos());

        return redirect()->route('gestion.carta.alergenos')
            ->with('estado', "Alérgeno «{$alergeno->nombre}» añadido al catálogo.");
    }

    /**
     * Formulario de modificación.
     *
     * @param  Alergeno  $alergeno  alérgeno de la dirección
     * @return View
     */
    public function editar(Alergeno $alergeno): View
    {
        return view('gestion.carta.alergeno', ['alergeno' => $alergeno]);
    }

    /**
     * Guarda los cambios de un alérgeno.
     *
     * @param  AlergenoRequest  $peticion  datos validados
     * @param  Alergeno  $alergeno  alérgeno de la dirección
     * @return RedirectResponse
     */
    public function actualizar(AlergenoRequest $peticion, Alergeno $alergeno): RedirectResponse
    {
        $alergeno->update($peticion->datos());

        return redirect()->route('gestion.carta.alergenos')
            ->with('estado', "Alérgeno «{$alergeno->nombre}» guardado.");
    }

    /**
     * Borra un alérgeno que no declara ningún producto. Si alguno lo declara,
     * vuelve al listado diciendo cuáles; la clave externa de
     * productos_alergenos lo impediría igualmente.
     *
     * @param  Alergeno  $alergeno  alérgeno de la dirección
     * @return RedirectResponse
     *
     * @throws QueryException si MySQL falla por otra causa
     */
    public function borrar(Alergeno $alergeno): RedirectResponse
    {
        if (! $alergeno->productos()->exists()) {
            try {
                $alergeno->delete();

                return redirect()->route('gestion.carta.alergenos')
                    ->with('estado', "Alérgeno «{$alergeno->nombre}» borrado del catálogo.");
            } catch (QueryException $error) {
                if (($error->errorInfo[1] ?? null) !== self::FILA_CON_HIJOS) {
                    throw $error;
                }
            }
        }

        return redirect()->route('gestion.carta.alergenos')->with('error', $this->avisoEnUso($alergeno));
    }

    /**
     * Explica qué productos declaran el alérgeno: los primeros por su nombre y
     * el resto como un número, para que el aviso no ocupe media pantalla.
     *
     * @param  Alergeno  $alergeno  alérgeno que no se ha podido borrar
     * @return string
     */
    private function avisoEnUso(Alergeno $alergeno): string
    {
        $nombres = $alergeno->productos()->orderBy('nombre')->pluck('nombre');
        $citados = $nombres->take(self::PRODUCTOS_EN_EL_AVISO)->map(fn (string $nombre) => "«{$nombre}»");
        $resto = $nombres->count() - self::PRODUCTOS_EN_EL_AVISO;

        $lista = $resto > 0 ? $citados->implode(', ')." y {$resto} más" : $citados->join(', ', ' y ');

        return "No se puede borrar «{$alergeno->nombre}» porque lo declaran estos productos: {$lista}. "
            .'Quítalo antes de cada producto si de verdad no lo llevan.';
    }
}
