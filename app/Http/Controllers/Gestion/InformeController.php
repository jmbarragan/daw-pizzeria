<?php

/**
 * Informes de la gestión, en pantalla y en PDF (RF-20, CU-17 y CU-18, pantalla G-08).
 */

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Servicios\Informes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * El empleado elige el informe y el intervalo, que por defecto es el mes en
 * curso. Una fecha inicial posterior a la final se rechaza (CU-17, 2a), y sin
 * datos se dice «No hay datos para estas fechas» y no se ofrece el PDF
 * (CU-17, 3a). El PDF sale de la misma consulta y de la misma tabla que la
 * pantalla. No cambia nada: solo consulta, con la conexión de solo lectura.
 */
class InformeController extends Controller
{
    /**
     * Informe elegido entre las dos fechas.
     *
     * @param  Request  $peticion  petición con «informe», «desde» y «hasta»
     * @param  Informes  $informes  cálculo de los informes
     * @return View pantalla con el informe y su total, o con el aviso de que no hay datos
     *
     * @throws ValidationException si el informe no existe o las fechas no valen; vuelve con el error
     */
    public function mostrar(Request $peticion, Informes $informes): View
    {
        return view('gestion.informes', $this->informe($peticion, $informes));
    }

    /**
     * El mismo informe en PDF, para descargarlo.
     *
     * @param  Request  $peticion  petición con «informe», «desde» y «hasta»
     * @param  Informes  $informes  cálculo de los informes
     * @return Response|RedirectResponse el PDF, o la vuelta a la pantalla si no hay datos
     *
     * @throws ValidationException si el informe no existe o las fechas no valen; vuelve con el error
     */
    public function pdf(Request $peticion, Informes $informes): Response|RedirectResponse
    {
        $datos = $this->informe($peticion, $informes);

        if ($datos['resultado']['filas'] === []) {
            return redirect()->route('gestion.informes', $peticion->only('informe', 'desde', 'hasta'))
                ->with('error', 'No hay datos para estas fechas, así que no hay PDF que descargar.');
        }

        $nombre = "informe-{$datos['informe']}-{$datos['desde']->toDateString()}-{$datos['hasta']->toDateString()}.pdf";

        // Con el subconjunto de la fuente, el PDF lleva solo los caracteres que usa y pesa unos KB, no casi 1 MB.
        // Para recortarla dompdf necesita una carpeta temporal donde escribir, y la del sistema puede no servir
        // (con php artisan serve en Windows es C:\WINDOWS). Va en la configuración porque dompdf la lee al crearse.
        config([
            'dompdf.options.enable_font_subsetting' => true,
            'dompdf.options.temp_dir' => storage_path('framework/cache'),
        ]);

        return Pdf::loadView('gestion.informe-pdf', $datos)->download($nombre);
    }

    /**
     * Valida la petición, con el mes en curso si faltan las fechas, y calcula el informe.
     *
     * @param  Request  $peticion  petición con «informe», «desde» y «hasta»
     * @param  Informes  $informes  cálculo de los informes
     * @return array{informe: string, desde: Carbon, hasta: Carbon, resultado: array{filas: list<array<string, mixed>>, total: array<string, mixed>}}
     *
     * @throws ValidationException si el informe no existe o las fechas no valen
     */
    private function informe(Request $peticion, Informes $informes): array
    {
        $peticion->merge([
            'informe' => $peticion->query('informe', 'ventas'),
            'desde' => $peticion->query('desde', today()->startOfMonth()->toDateString()),
            'hasta' => $peticion->query('hasta', today()->endOfMonth()->toDateString()),
        ]);
        $datos = $peticion->validate([
            'informe' => ['required', Rule::in(array_keys(Informes::TIPOS))],
            'desde' => ['required', 'date_format:Y-m-d'],
            'hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha inicial no puede ser posterior a la final.',
            'date_format' => 'Elige una fecha del calendario.',
        ]);

        $desde = Carbon::parse($datos['desde']);
        $hasta = Carbon::parse($datos['hasta']);

        return [
            'informe' => $datos['informe'],
            'desde' => $desde,
            'hasta' => $hasta,
            'resultado' => $informes->calcular($datos['informe'], $desde, $hasta),
        ];
    }
}
