<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

// Reports
use App\Reports\VentasReport;
use App\Reports\TecnicosTopReport;
use App\Reports\EntradasInventarioReport;
use App\Reports\SalidasInventarioReport;
use App\Reports\StockCriticoReport;
use App\Reports\ClientesTopReport;
use App\Reports\CotizacionesEstadoReport;

// Export / PDF
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReporteGenericoExport;
use App\Exports\Reportes\VentasExport;
use App\Exports\Reportes\TecnicosTopExport;
use App\Exports\Reportes\EntradasInventarioExport;
use App\Exports\Reportes\SalidasInventarioExport;
use App\Exports\Reportes\StockCriticoExport;
use App\Exports\Reportes\ClientesTopExport;
use App\Exports\Reportes\CotizacionesEstadoExport;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ReporteController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            $tipo = $request->query('tipo', 'ventas');
            $d    = $request->query('desde');
            $h    = $request->query('hasta');

            [$desde, $hasta] = $this->parseRango($d, $h);

            // --- Moneda ---
            $moneda     = $this->normalizarMoneda($request->query('moneda', 'MXN'));
            $tipoCambio = $this->obtenerTipoCambio($moneda);

            $data = $this->buildData($tipo, $desde, $hasta);

            $data['moneda']      = $moneda;
            $data['tipo_cambio'] = $tipoCambio;

            return response()->json($data);
        }

        $view = view()->exists('vistas-gerente.reportes.index')
            ? 'vistas-gerente.reportes.index'
            : 'vistas-gerente.reportes';

        return view($view);
    }

    public function descargar(Request $request)
    {
        $formato = strtolower($request->query('formato', 'pdf'));
        $tipo    = $request->query('tipo', 'ventas');

        // ✅ guardamos crudo (para exports que lo usan tal cual)
        $desdeRaw = $request->query('desde');
        $hastaRaw = $request->query('hasta');

        [$desde, $hasta] = $this->parseRango($desdeRaw, $hastaRaw);

        // --- Moneda ---
        $moneda     = $this->normalizarMoneda($request->query('moneda', 'MXN'));
        $tipoCambio = $this->obtenerTipoCambio($moneda);

        $data = $this->buildData($tipo, $desde, $hasta);
        $data['moneda']      = $moneda;
        $data['tipo_cambio'] = $tipoCambio;

        $titulos = [
            'ventas'              => 'Reporte de Ventas',
            'productos_top'       => 'Productos Más Vendidos',
            'productos_bottom'    => 'Productos Menos Vendidos',
            'tecnicos_top'        => 'Técnicos con Más Ventas',
            'entradas'            => 'Entradas de Inventario',
            'salidas'             => 'Salidas de Inventario',
            'stock_critico'       => 'Stock Crítico',
            'clientes_top'        => 'Clientes con Más Compras',
            'cotizaciones_estado' => 'Cotizaciones por Estado',
        ];

        $titulo = $titulos[$tipo] ?? 'Reporte';

        if ($desde && $hasta) {
            $rango = sprintf('Del %s al %s', $desde->format('d/m/Y'), $hasta->format('d/m/Y'));
        } elseif ($desde) {
            $rango = 'Desde ' . $desde->format('d/m/Y');
        } elseif ($hasta) {
            $rango = 'Hasta ' . $hasta->format('d/m/Y');
        } else {
            $rango = 'Sin rango de fechas';
        }

        // ========= PDF =========
        if ($formato === 'pdf') {
            $view = "vistas-gerente.reportes.pdf.$tipo";

            if (!view()->exists($view)) {
                abort(404, "Vista PDF no encontrada para el tipo de reporte [$tipo].");
            }

            $pdf = Pdf::loadView($view, array_merge($data, [
                'titulo'      => $titulo,
                'rango'       => $rango,
                'moneda'      => $moneda,
                'tipo_cambio' => $tipoCambio,
            ]));

            $filename = str_replace(' ', '_', strtolower($titulo)) . '.pdf';
            return $pdf->download($filename);
        }

        // ========= EXCEL =========
        if ($formato === 'excel') {
            $colsAssoc = $data['cols'] ?? [];
            $rowsAssoc = $data['rows'] ?? [];
            $meta      = $data['meta'] ?? [];

            // Base: filas en el orden de cols (muchos exports las usan así)
            $rows = array_map(function ($r) use ($colsAssoc) {
                return array_map(fn($c) => $r[$c] ?? '', $colsAssoc);
            }, $rowsAssoc);

            // ✅ VENTAS: convertir montos a números (float) para que Excel aplique formatos y gráfica correctamente
            if ($tipo === 'ventas') {
                $rows = $this->rowsVentasToNumeric($colsAssoc, $rowsAssoc);
            }

            $filename = str_replace(' ', '_', strtolower($titulo)) . '.xlsx';

            switch ($tipo) {
                case 'ventas':
                    $export = new VentasExport(
                        $titulo,
                        $colsAssoc,
                        $rows,
                        $meta,
                        $tipoCambio
                    );
                    break;

                case 'tecnicos_top':
                    // ✅ este export recibe el dataset completo (cols/rows/meta)
                    $export = new TecnicosTopExport($data, $titulo);
                    break;

                case 'entradas':
                    // (si aún no has actualizado este export, lo dejamos como estaba)
                    $export = new EntradasInventarioExport($colsAssoc, $rows, $moneda, $tipoCambio);
                    break;

                case 'salidas':
                    // ✅ este export se construye solo con el rango
                    $export = new SalidasInventarioExport($desdeRaw, $hastaRaw);
                    break;

                case 'stock_critico':
                    // ✅ firma correcta (titulo, cols, rows)
                    $export = new StockCriticoExport($titulo, $colsAssoc, $rows);
                    break;

                case 'clientes_top':
                    $export = new ClientesTopExport(
                        $titulo,
                        $colsAssoc,
                        $rows,
                        $meta
                    );
                    break;

                case 'cotizaciones_estado':
                    $export = new CotizacionesEstadoExport(
                        $colsAssoc,
                        $rows,
                        $moneda,
                        $tipoCambio
                    );
                    break;

                default:
                    $export = new ReporteGenericoExport(
                        $colsAssoc,
                        $rows,
                        $titulo,
                        $moneda,
                        $tipoCambio,
                        $meta
                    );
                    break;
            }

            return Excel::download($export, $filename);
        }

        abort(400, 'Formato no soportado.');
    }

    protected function buildData(string $tipo, $desde = null, $hasta = null): array
    {
        switch ($tipo) {
            case 'ventas':
                return app(VentasReport::class)->build($desde, $hasta);

            case 'tecnicos_top':
                return app(TecnicosTopReport::class)->build($desde, $hasta);

            case 'entradas':
                return app(EntradasInventarioReport::class)->build($desde, $hasta);

            case 'salidas':
                return app(SalidasInventarioReport::class)->build($desde, $hasta);

            case 'stock_critico':
                return app(StockCriticoReport::class)->build($desde, $hasta);

            case 'clientes_top':
                return app(ClientesTopReport::class)->build($desde, $hasta);

            case 'cotizaciones_estado':
                return app(CotizacionesEstadoReport::class)->build($desde, $hasta);

            default:
                return ['cols' => [], 'rows' => [], 'chart' => [], 'meta' => []];
        }
    }

    protected function normalizarMoneda(?string $moneda): string
    {
        $m = strtoupper($moneda ?? 'MXN');
        return in_array($m, ['MXN', 'USD']) ? $m : 'MXN';
    }

    protected function obtenerTipoCambio(string $moneda): float
    {
        return Cache::remember('reportes.tipo_cambio_usd_mxn', 60 * 60 * 24, function () {
            $apiKey = config('services.exchange_rate.key');
            $fallback = 18.0;

            if (!$apiKey) {
                return $fallback;
            }

            try {
                $response = Http::timeout(10)->get(
                    "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD"
                );

                if (!$response->successful()) {
                    return $fallback;
                }

                $data  = $response->json();
                $rates = $data['conversion_rates'] ?? [];
                $mxn   = $rates['MXN'] ?? null;

                return $mxn ?: $fallback;
            } catch (\Throwable $e) {
                return $fallback;
            }
        });
    }

    protected function parseRango($d, $h): array
    {
        $desde = $d ? Carbon::parse($d)->startOfDay() : null;
        $hasta = $h ? Carbon::parse($h)->endOfDay()   : null;

        return [$desde, $hasta];
    }

    /**
     * ✅ Convierte filas de Ventas (asociativas) a filas numéricas, asegurando que los montos
     * vayan como float (sin signos $ / US$) para que VentasExport aplique formatos y gráfica.
     */
    protected function rowsVentasToNumeric(array $colsAssoc, array $rowsAssoc): array
    {
        $moneyCols = [
            'Total productos',
            'Costo servicio',
            'Costo operativo',
            'Total servicios',
            'Materiales no previstos',
            'Total orden',
            'Total pagado',
            'Saldo',
            // compatibilidad
            'Total general',
            'Anticipo',
        ];

        $out = [];

        foreach ($rowsAssoc as $r) {
            $row = [];

            foreach ($colsAssoc as $c) {
                $v = $r[$c] ?? '';

                if (in_array($c, $moneyCols, true)) {
                    $row[] = $this->toNumber($v);
                } else {
                    $row[] = $v;
                }
            }

            $out[] = $row;
        }

        return $out;
    }

    protected function toNumber($value): float
    {
        if ($value === null) return 0.0;

        $s = trim((string)$value);

        if ($s === '' || $s === '—' || $s === '-') return 0.0;

        $s = str_replace(['US$', '$', ',', ' ', 'MXN', 'USD'], '', $s);

        return is_numeric($s) ? (float)$s : 0.0;
    }
}
