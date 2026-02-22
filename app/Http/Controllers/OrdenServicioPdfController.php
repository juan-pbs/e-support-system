<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

use App\Services\Ordenes\OrdenServicioService;

use App\Models\User;
use App\Models\Cliente;
use App\Models\OrdenServicio;

use Barryvdh\DomPDF\Facade\Pdf;

class OrdenServicioPdfController extends Controller
{
    public function __construct(private OrdenServicioService $svc) {}

    /**
     * PREVIEW (NO guarda nada, NO consume inventario)
     * - Valida formulario
     * - Pre-carga N/S sugeridos si el producto es serial y no vienen asignados
     * - Revisa stock
     * - Genera PDF base64
     */
    public function previewPdf(Request $request)
    {
        $data = $this->svc->validateOrden($request, false);

        // ✅ Preparar líneas (para preview) con N/S sugeridos si aplica
        $productosPreview = $this->svc->prepareLineItemsWithSerials($data['productos'] ?? []);

        // ✅ Validar stock con el mismo array que usaremos para PDF/UI
        $check = $this->svc->preflightStockCheck($productosPreview);
        if (!($check['ok'] ?? false)) {
            return response()->json([
                'ok'                => false,
                'message'           => 'Hay productos sin stock suficiente.',
                'shortages'         => $check['shortages'] ?? [],
                'productos_preview' => $check['annotated'] ?? [],
            ], 422);
        }

        $orden = new OrdenServicio();
        $this->svc->fillOrden($orden, $data);

        // Técnicos (múltiples)
        $idsTecnicos = array_map('intval', $data['tecnicos_ids'] ?? []);
        $orden->setRelation(
            'tecnicos',
            !empty($idsTecnicos)
                ? User::whereIn('id', $idsTecnicos)->get(['id', 'name'])
                : collect()
        );

        // Totales / IVA
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

        $orden->impuestos = (float) ($totales['iva'] ?? 0);

        // ✅ Anticipo / saldo (se guarda en el objeto $orden para que el PDF lo muestre)
        $this->svc->applyAnticipoToOrden($orden, $data, $totales);

        $cliente = Cliente::findOrFail((int) $data['id_cliente']);

        // Map a objetos para el PDF
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

        // Firma (preferencia: input del form -> default guardada)
        $firma = $this->svc->getFirma();

        $firmaIn = $request->input('firma_base64')
            ?: $request->input('firma_autorizacion')
            ?: $request->input('firma_autorizacion_base64');

        if ($firmaIn && strpos($firmaIn, 'data:image/') !== 0) {
            if (strpos($firmaIn, 'base64,') === false) {
                $firmaIn = 'data:image/png;base64,' . $firmaIn;
            }
        }

        config([
            'dompdf.options.isRemoteEnabled'      => true,
            'dompdf.options.isHtml5ParserEnabled' => true,
        ]);

        $pdf = Pdf::loadView('pdf.orden_servicio', [
            'orden'        => $orden,
            'cliente'      => $cliente,
            'productos'    => $productosMapped,
            'firma_base64' => $firmaIn,
            'firma'        => $firma,
        ])->setPaper('letter')->setOptions([
            'isRemoteEnabled'      => true,
            'isHtml5ParserEnabled' => true,
        ]);

        return response()->json([
            'ok'                => true,
            'pdf_base64'        => base64_encode($pdf->output()),
            // ✅ Devolver lo mismo que ve el usuario (con stock + N/S)
            'productos_preview' => $check['annotated'] ?? [],
        ]);
    }

    /**
     * PDF DEFINITIVO
     * - Si existe archivo_pdf, lo sirve
     * - Si no existe, lo genera, lo guarda y lo sirve
     */
    public function pdf(Request $request, $id)
    {
        $orden = OrdenServicio::with(['cliente', 'tecnicos'])->findOrFail($id);

        $isCompra = (string) $orden->tipo_orden === 'compra';
        $prefijo  = $isCompra ? 'OC' : 'OS';
        $baseName = $isCompra ? 'orden-compra' : 'orden-servicio';
        $filename = $baseName . '-' . $prefijo . '-' . $orden->id_orden_servicio . '.pdf';

        // ✅ Forzar regeneración (útil si ya existía un archivo antiguo sin N/S).
        if ($request->boolean('refresh')) {
            $this->svc->deleteArchivoPdfIfExists($orden);
            $orden->archivo_pdf = null;
            $orden->save();
        }

        if (!empty($orden->archivo_pdf) && Storage::disk('public')->exists($orden->archivo_pdf)) {
            return $this->svc->responsePublicPdf($orden->archivo_pdf, $filename, $request->boolean('download'));
        }

        $path = $this->svc->generarYGuardarPdfOrden((int) $orden->getKey());
        return $this->svc->responsePublicPdf($path, $filename, $request->boolean('download'));
    }
}
