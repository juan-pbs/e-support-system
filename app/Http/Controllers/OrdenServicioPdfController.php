<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\OrdenServicio;
use App\Models\User;
use App\Services\Ordenes\OrdenServicioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrdenServicioPdfController extends Controller
{
    public function __construct(private OrdenServicioService $svc) {}

    public function previewPdf(Request $request)
    {
        $data  = $this->svc->validateOrden($request, false);
        $token = !empty($data['serial_token']) ? (string) $data['serial_token'] : null;

        // ✅ IMPORTANTE:
        // respetar el token actual para que la preview no bloquee
        // seriales que el mismo formulario ya reservó
        $productosPreview = $this->svc->prepareLineItemsWithSerials(
            $data['productos'] ?? [],
            $token
        );

        $check = ((int) $request->input('orden_id_context', 0) > 0) ? ['ok' => true, 'shortages' => [], 'annotated' => $productosPreview] : $this->svc->preflightStockCheck($productosPreview, $token);

        if (!($check['ok'] ?? false)) {
            return response()->json([
                'ok'                => false,
                'message'           => 'Hay productos sin stock suficiente.',
                'shortages'         => $check['shortages'] ?? [],
                'productos_preview' => $check['annotated'] ?? $productosPreview,
            ], 422);
        }

        $orden = new OrdenServicio();
        $this->svc->fillOrden($orden, $data);

        $idsTecnicos = array_map('intval', $data['tecnicos_ids'] ?? []);
        $orden->setRelation(
            'tecnicos',
            !empty($idsTecnicos)
                ? User::whereIn('id', $idsTecnicos)->get(['id', 'name'])
                : new Collection()
        );

        $adicional = 0.0;
        try {
            $adicional = (float) ($orden->total_adicional ?? 0);
        } catch (\Throwable $e) {
            $adicional = 0.0;
        }

        $totales = $this->svc->calculateTotals(
            $productosPreview,
            (float) ($orden->precio ?? 0),
            (float) ($orden->costo_operativo ?? 0),
            $adicional
        );

        if (property_exists($orden, 'impuestos') || isset($orden->impuestos)) {
            $orden->impuestos = (float) ($totales['iva'] ?? 0);
        }

        $orden->precio_escrito = $this->svc->resolvePrecioEscrito(
            $data['precio_escrito'] ?? ($orden->precio_escrito ?? null),
            (float) ($totales['total'] ?? 0),
            (string) ($orden->moneda ?? 'MXN')
        );

        $this->svc->applyAnticipoToOrden($orden, $data, $totales);

        $cliente = Cliente::findOrFail((int) $data['id_cliente']);

        $productosMapped = array_map(function ($i) {
            $qty = $this->svc->quantityFrom($i);
            $pu  = $this->svc->unitPriceFrom($i);

            return (object) [
                'nombre_producto' => $i['nombre_producto'] ?? ($i['descripcion'] ?? 'Producto'),
                'descripcion'     => $i['descripcion'] ?? null,
                'detalle'         => null,
                'cantidad'        => $qty,
                'precio_unitario' => $pu,
                'total'           => max(($qty * $pu), 0),
                'ns_asignados'    => $i['ns_asignados'] ?? [],
            ];
        }, $productosPreview);

        $firma = $this->svc->getFirma();

        $firmaIn = $request->input('firma_base64')
            ?: $request->input('firma_autorizacion')
            ?: $request->input('firma_autorizacion_base64');

        $firmaBase64 = $this->svc->normalizeDataUriImage($firmaIn ?: ($firma['image'] ?? null));

        config([
            'dompdf.options.isRemoteEnabled'      => true,
            'dompdf.options.isHtml5ParserEnabled' => true,
        ]);

        $pdf = Pdf::loadView('pdf.orden_servicio', [
            'orden'        => $orden,
            'cliente'      => $cliente,
            'productos'    => $productosMapped,
            'firma_base64' => $firmaBase64,
            'firma'        => $firma,
        ])->setPaper('letter')->setOptions([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
        ]);

        return response()->json([
            'ok'                => true,
            'pdf_base64'        => base64_encode($pdf->output()),
            'productos_preview' => $productosPreview,
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $orden = OrdenServicio::findOrFail($id);

        if (empty($orden->archivo_pdf) || !\Storage::disk('public')->exists($orden->archivo_pdf)) {
            $this->svc->generarYGuardarPdfOrden((int) $orden->getKey());
            $orden->refresh();
        }

        $download = $request->boolean('download');
        $filename = 'orden_servicio_' . $orden->getKey() . '.pdf';

        return $this->svc->responsePublicPdf($orden->archivo_pdf, $filename, $download);
    }
}
