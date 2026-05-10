<?php

namespace App\Services\Logistica;

use App\Models\ClienteDireccionLogistica;
use App\Models\Inventario;
use App\Models\JornadaLogistica;
use App\Models\MovimientoLogistico;
use App\Models\MovimientoLogisticoDetalle;
use App\Models\MovimientoLogisticoEvidencia;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Ordenes\OrdenServicioService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class LogisticaService
{
    public function __construct(private OrdenServicioService $ordenes) {}

    public function generarFolioJornada(): string
    {
        $next = ((int) JornadaLogistica::max('id')) + 1;

        return 'JL-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    public function crearJornada(array $data, ?User $user = null): JornadaLogistica
    {
        return JornadaLogistica::create([
            'folio' => $data['folio'] ?? $this->generarFolioJornada(),
            'nombre' => $data['nombre'] ?? null,
            'fecha' => $data['fecha'] ?? now()->toDateString(),
            'estado' => 'abierta',
            'created_by' => $user?->id,
            'opened_at' => now(),
            'observaciones' => $data['observaciones'] ?? null,
        ]);
    }

    public function cerrarJornada(JornadaLogistica $jornada, ?User $user = null): void
    {
        if ($jornada->estado === 'cerrada') {
            return;
        }

        $jornada->estado = 'cerrada';
        $jornada->closed_by = $user?->id;
        $jornada->closed_at = now();
        $jornada->save();
    }

    public function getJornadaAbiertaActual(): ?JornadaLogistica
    {
        return JornadaLogistica::query()
            ->where('estado', 'abierta')
            ->latest('opened_at')
            ->latest('id')
            ->first();
    }

    public function syncEntregaDesdeOrden(OrdenServicio $orden): ?MovimientoLogistico
    {
        $orden->loadMissing(['cliente.direccionesLogisticas', 'direccionCliente', 'productos', 'tecnicos']);

        $movimiento = MovimientoLogistico::query()
            ->where('orden_servicio_id', $orden->id_orden_servicio)
            ->where('tipo', 'entrega')
            ->latest('id')
            ->first();

        $direccion = $orden->direccionCliente
            ?: $orden->cliente?->direccionesLogisticas->firstWhere('predeterminada', true)
            ?: $orden->cliente?->direccionesLogisticas->first();

        $debeExistir =
            $this->ordenes->isEntregaVentaType((string) $orden->tipo_orden)
            && (bool) ($orden->requiere_logistica ?? false)
            && $direccion
            && $direccion->verificada_en_mapa
            && !is_null($direccion->latitud)
            && !is_null($direccion->longitud);

        if (!$debeExistir) {
            if ($movimiento && !in_array($movimiento->estado, ['entregado', 'cancelado'], true)) {
                $movimiento->estado = 'cancelado';
                $movimiento->incidencia_descripcion = 'Movimiento cancelado desde la orden de servicio.';
                $movimiento->save();
            }

            return null;
        }

        $cliente = $orden->cliente;
        $jornada = $movimiento?->jornada ?: $this->getJornadaAbiertaActual();

        $movimiento ??= new MovimientoLogistico([
            'tipo' => 'entrega',
            'origen_tipo' => 'orden_servicio',
            'origen_id' => $orden->id_orden_servicio,
        ]);

        $movimiento->jornada_logistica_id = $jornada?->id;
        $movimiento->orden_servicio_id = $orden->id_orden_servicio;
        $movimiento->clave_cliente = $cliente?->clave_cliente;
        $movimiento->cliente_direccion_id = $direccion->id;
        if (!$movimiento->exists) {
            $movimiento->tecnico_id = null;
        }
        $movimiento->contacto = $cliente?->contacto ?: $cliente?->nombre;
        $movimiento->telefono = $cliente?->telefono;
        $movimiento->alias_direccion = $direccion->alias;
        $movimiento->direccion_formateada = $direccion->direccion_formateada;
        $movimiento->place_id = $direccion->place_id;
        $movimiento->latitud = $direccion->latitud;
        $movimiento->longitud = $direccion->longitud;
        $movimiento->referencia = $direccion->referencia;
        $movimiento->fecha_programada = $orden->fecha_orden ?: now()->toDateString();
        $movimiento->payload = array_merge($movimiento->payload ?? [], [
            'folio_orden' => $orden->folio,
            'moneda' => $orden->moneda,
            'tipo_pago' => $orden->tipo_pago,
        ]);

        if (!$movimiento->exists) {
            $movimiento->estado = 'pendiente';
        }

        if ($movimiento->estado === 'cancelado') {
            $movimiento->estado = 'pendiente';
            $movimiento->incidencia_descripcion = null;
        }

        $movimiento->save();

        $this->sincronizarDetallesEntrega($movimiento, $orden);

        return $movimiento;
    }

    public function cancelarMovimientosPorOrden(OrdenServicio $orden): void
    {
        MovimientoLogistico::query()
            ->where('orden_servicio_id', $orden->id_orden_servicio)
            ->whereNotIn('estado', ['entregado', 'cancelado'])
            ->update([
                'estado' => 'cancelado',
                'incidencia_descripcion' => 'Movimiento cancelado al eliminar la orden.',
                'updated_at' => now(),
            ]);
    }

    protected function sincronizarDetallesEntrega(MovimientoLogistico $movimiento, OrdenServicio $orden): void
    {
        $movimiento->detalles()->delete();

        foreach ($orden->productos as $detalle) {
            $movimiento->detalles()->create([
                'codigo_producto' => $detalle->codigo_producto,
                'nombre_producto' => $detalle->nombre_producto ?? ('Producto ' . $detalle->codigo_producto),
                'cantidad' => (float) ($detalle->cantidad ?? 0),
                'unidad' => $detalle->unidad ?? null,
                'tipo_control' => null,
                'payload' => [
                    'descripcion' => $detalle->descripcion ?? null,
                    'precio_unitario' => (float) ($detalle->precio_unitario ?? 0),
                    'total' => (float) ($detalle->total ?? 0),
                ],
            ]);
        }
    }

    public function programarRecoleccionInventario(array $data, ?User $actor = null): MovimientoLogistico
    {
        $producto = Producto::query()->findOrFail((int) $data['codigo_producto']);
        $proveedor = Proveedor::query()->findOrFail((int) $data['clave_proveedor']);

        if (
            empty($proveedor->direccion_logistica)
            || !$proveedor->direccion_logistica_verificada_en_mapa
            || is_null($proveedor->direccion_logistica_latitud)
            || is_null($proveedor->direccion_logistica_longitud)
        ) {
            throw ValidationException::withMessages([
                'clave_proveedor' => 'El proveedor necesita una dirección logística verificada en mapa para programar una recolección.',
            ]);
        }

        $jornadaId = !empty($data['jornada_logistica_id'])
            ? (int) $data['jornada_logistica_id']
            : $this->getJornadaAbiertaActual()?->id;

        return DB::transaction(function () use ($data, $actor, $producto, $proveedor, $jornadaId) {
            $movimiento = MovimientoLogistico::create([
                'jornada_logistica_id' => $jornadaId,
                'clave_proveedor' => $proveedor->clave_proveedor,
                'tecnico_id' => !empty($data['tecnico_logistica_id']) ? (int) $data['tecnico_logistica_id'] : null,
                'tipo' => 'recoleccion',
                'origen_tipo' => 'inventario_programado',
                'contacto' => $proveedor->contacto ?: $proveedor->nombre,
                'telefono' => $proveedor->telefono,
                'alias_direccion' => $proveedor->alias ?: $proveedor->nombre,
                'direccion_formateada' => $proveedor->direccion_logistica ?: $proveedor->direccion,
                'place_id' => $proveedor->direccion_logistica_place_id,
                'latitud' => $proveedor->direccion_logistica_latitud,
                'longitud' => $proveedor->direccion_logistica_longitud,
                'referencia' => $proveedor->direccion_logistica_referencia,
                'estado' => 'pendiente',
                'fecha_programada' => $data['fecha_programada'] ?? now()->toDateString(),
                'hora_programada' => $data['hora_programada'] ?? null,
                'observaciones' => $data['observaciones_logistica'] ?? null,
                'payload' => [
                    'forma_ingreso' => 'recoleccion_programada',
                    'creado_por' => $actor?->id,
                ],
            ]);

            $payload = [
                'codigo_producto' => $producto->codigo_producto,
                'clave_proveedor' => $proveedor->clave_proveedor,
                'costo' => (float) $data['costo'],
                'precio' => isset($data['precio']) ? (float) $data['precio'] : 0,
                'tipo_control' => (string) $data['tipo_control'],
                'cantidad_ingresada' => !empty($data['cantidad_ingresada']) ? (int) $data['cantidad_ingresada'] : null,
                'piezas_por_paquete' => !empty($data['piezas_por_paquete']) ? (int) $data['piezas_por_paquete'] : null,
                'seriales' => $this->parseSeriesFromData($data),
                'fecha_caducidad' => $data['fecha_caducidad'] ?? null,
            ];

            $movimiento->detalles()->create([
                'codigo_producto' => $producto->codigo_producto,
                'nombre_producto' => $producto->nombre,
                'cantidad' => $this->cantidadMovimientoDesdePayload($payload),
                'unidad' => $producto->unidad,
                'tipo_control' => $payload['tipo_control'],
                'seriales' => $payload['seriales'],
                'payload' => $payload,
            ]);

            return $movimiento;
        });
    }

    public function confirmarRecepcionRecoleccion(MovimientoLogistico $movimiento, ?User $actor = null): void
    {
        if ($movimiento->tipo !== 'recoleccion') {
            throw ValidationException::withMessages([
                'movimiento' => 'Solo las recolecciones pueden confirmarse como entrada de inventario.',
            ]);
        }

        if ($movimiento->estado !== 'recogido') {
            throw ValidationException::withMessages([
                'movimiento' => 'La recepción solo puede confirmarse cuando el técnico ya marcó la recolección como recogida.',
            ]);
        }

        if ($movimiento->recepcion_confirmada_at) {
            return;
        }

        $movimiento->loadMissing('detalles');

        DB::transaction(function () use ($movimiento, $actor) {
            foreach ($movimiento->detalles as $detalle) {
                $payload = $detalle->payload ?? [];
                $this->crearEntradaInventarioDesdeDetalle($movimiento, $detalle, $payload);
            }

            $movimiento->recepcion_confirmada_at = now();
            $movimiento->recepcion_confirmada_por = $actor?->id;
            $movimiento->save();
        });
    }

    protected function crearEntradaInventarioDesdeDetalle(
        MovimientoLogistico $movimiento,
        MovimientoLogisticoDetalle $detalle,
        array $payload
    ): void {
        $codigoProducto = (int) ($payload['codigo_producto'] ?? $detalle->codigo_producto);
        $tipo = (string) ($payload['tipo_control'] ?? $detalle->tipo_control);
        $fechaEntrada = Carbon::now()->toDateString();
        $horaEntrada = Carbon::now()->format('H:i:s');

        $base = [
            'codigo_producto' => $codigoProducto,
            'clave_proveedor' => $movimiento->clave_proveedor,
            'costo' => (float) ($payload['costo'] ?? 0),
            'precio' => (float) ($payload['precio'] ?? 0),
            'tipo_control' => $tipo,
            'fecha_entrada' => $fechaEntrada,
            'hora_entrada' => $horaEntrada,
            'fecha_caducidad' => $payload['fecha_caducidad'] ?? null,
            'forma_ingreso' => 'recoleccion_programada',
            'estado_recepcion' => 'recibido',
            'movimiento_logistico_id' => $movimiento->id,
        ];

        if ($tipo === 'SERIE') {
            $series = collect($payload['seriales'] ?? [])->filter()->values();

            if ($series->isEmpty()) {
                throw ValidationException::withMessages([
                    'numeros_serie' => 'La recolección programada necesita números de serie válidos para confirmar la entrada.',
                ]);
            }

            $duplicadas = Inventario::query()
                ->whereIn('numero_serie', $series->all())
                ->pluck('numero_serie')
                ->unique()
                ->values();

            if ($duplicadas->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'numeros_serie' => 'Estas series ya existen en inventario: ' . $duplicadas->implode(', '),
                ]);
            }

            foreach ($series as $serie) {
                Inventario::create(array_merge($base, [
                    'cantidad_ingresada' => 1,
                    'piezas_por_paquete' => null,
                    'paquetes_restantes' => 0,
                    'piezas_sueltas' => 1,
                    'numero_serie' => $serie,
                ]));
            }
        } elseif ($tipo === 'PAQUETES') {
            $paquetes = max((int) ($payload['cantidad_ingresada'] ?? 0), 1);
            $piezasPorPaquete = max((int) ($payload['piezas_por_paquete'] ?? 0), 1);

            Inventario::create(array_merge($base, [
                'cantidad_ingresada' => $paquetes,
                'piezas_por_paquete' => $piezasPorPaquete,
                'paquetes_restantes' => $paquetes,
                'piezas_sueltas' => 0,
                'numero_serie' => null,
            ]));
        } else {
            $piezas = max((int) ($payload['cantidad_ingresada'] ?? $detalle->cantidad ?? 0), 1);

            Inventario::create(array_merge($base, [
                'cantidad_ingresada' => $piezas,
                'piezas_por_paquete' => null,
                'paquetes_restantes' => 0,
                'piezas_sueltas' => $piezas,
                'numero_serie' => null,
            ]));
        }

        $this->ordenes->refreshProductStockTotals($codigoProducto);
    }

    public function cambiarEstadoTecnico(
        MovimientoLogistico $movimiento,
        string $estado,
        ?float $latitudActual = null,
        ?float $longitudActual = null
    ): void {
        if (in_array($movimiento->estado, ['recogido', 'entregado', 'cancelado'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Este movimiento ya está cerrado y no admite más cambios de estado.',
            ]);
        }

        if ($estado === 'en_ruta') {
            $movimiento->estado = 'en_ruta';
            $movimiento->fecha_inicio ??= now();
            $movimiento->save();
            return;
        }

        if ($estado === 'en_sitio') {
            $distancia = $this->validarUbicacionEnSitio($movimiento, $latitudActual, $longitudActual);
            $movimiento->estado = 'en_sitio';
            $movimiento->fecha_llegada = now();
            $movimiento->llegada_latitud = $latitudActual;
            $movimiento->llegada_longitud = $longitudActual;
            $movimiento->distancia_metros = $distancia;
            $movimiento->save();
            return;
        }

        throw ValidationException::withMessages([
            'estado' => 'Estado logístico no soportado para esta acción.',
        ]);
    }

    public function completarMovimientoTecnico(
        MovimientoLogistico $movimiento,
        array $evidencias,
        ?string $comentario,
        ?float $latitudActual,
        ?float $longitudActual,
        User $tecnico
    ): void {
        if (in_array($movimiento->estado, ['recogido', 'entregado', 'cancelado'], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Este movimiento ya fue cerrado anteriormente.',
            ]);
        }

        $files = collect($evidencias)
            ->filter(fn ($file) => $file instanceof UploadedFile)
            ->values();

        $distancia = $this->validarUbicacionEnSitio($movimiento, $latitudActual, $longitudActual);

        DB::transaction(function () use ($movimiento, $files, $comentario, $latitudActual, $longitudActual, $tecnico, $distancia) {
            foreach ($files as $index => $file) {
                /** @var UploadedFile $file */
                $path = $file->store('logistica/evidencias', 'public');

                MovimientoLogisticoEvidencia::create([
                    'movimiento_logistico_id' => $movimiento->id,
                    'tipo_foto' => 'evidencia_' . ($index + 1),
                    'ruta_archivo' => $path,
                    'latitud' => $latitudActual,
                    'longitud' => $longitudActual,
                    'tomado_en' => now(),
                    'comentario' => $comentario,
                    'created_by' => $tecnico->id,
                ]);
            }

            $movimiento->estado = $movimiento->tipo === 'recoleccion' ? 'recogido' : 'entregado';
            $movimiento->fecha_cierre = now();
            $movimiento->cierre_latitud = $latitudActual;
            $movimiento->cierre_longitud = $longitudActual;
            $movimiento->distancia_metros = $distancia;
            $movimiento->observaciones = $comentario ?: $movimiento->observaciones;
            $movimiento->save();
        });
    }

    public function actualizarAsignacionMovimiento(MovimientoLogistico $movimiento, array $data): void
    {
        $movimiento->fill([
            'jornada_logistica_id' => !empty($data['jornada_logistica_id']) ? (int) $data['jornada_logistica_id'] : null,
            'tecnico_id' => !empty($data['tecnico_id']) ? (int) $data['tecnico_id'] : null,
            'fecha_programada' => $data['fecha_programada'] ?? $movimiento->fecha_programada,
            'hora_programada' => $data['hora_programada'] ?? $movimiento->hora_programada,
            'observaciones' => $data['observaciones'] ?? $movimiento->observaciones,
        ]);
        $movimiento->save();
    }

    public function puedeTecnicoVerMovimiento(MovimientoLogistico $movimiento, User $tecnico): bool
    {
        if ((int) $movimiento->tecnico_id === (int) $tecnico->id) {
            return true;
        }

        return $this->movimientoDisponibleParaCualquierTecnico($movimiento);
    }

    public function asegurarTecnicoResponsable(MovimientoLogistico $movimiento, User $tecnico): void
    {
        DB::transaction(function () use ($movimiento, $tecnico) {
            /** @var MovimientoLogistico $fresh */
            $fresh = MovimientoLogistico::query()
                ->lockForUpdate()
                ->findOrFail($movimiento->id);

            if ((int) $fresh->tecnico_id === (int) $tecnico->id) {
                $movimiento->forceFill($fresh->getAttributes());
                $movimiento->syncOriginal();
                return;
            }

            if (!$this->movimientoDisponibleParaCualquierTecnico($fresh)) {
                throw ValidationException::withMessages([
                    'movimiento' => 'Este movimiento ya fue tomado por otro técnico o ya no está disponible.',
                ]);
            }

            $fresh->tecnico_id = $tecnico->id;
            $fresh->save();

            $movimiento->forceFill($fresh->getAttributes());
            $movimiento->syncOriginal();
        });
    }

    public function validarUbicacionEnSitio(
        MovimientoLogistico $movimiento,
        ?float $latitudActual,
        ?float $longitudActual
    ): float {
        if (is_null($latitudActual) || is_null($longitudActual)) {
            throw ValidationException::withMessages([
                'ubicacion_actual' => 'Necesitamos la ubicación actual del técnico para registrar esta acción.',
            ]);
        }

        if (is_null($movimiento->latitud) || is_null($movimiento->longitud)) {
            throw ValidationException::withMessages([
                'destino' => 'El movimiento no tiene coordenadas válidas para validar la llegada.',
            ]);
        }

        $distancia = $this->distanciaEnMetros(
            (float) $latitudActual,
            (float) $longitudActual,
            (float) $movimiento->latitud,
            (float) $movimiento->longitud
        );

        if ($distancia > (int) ($movimiento->radio_validacion_metros ?? 150)) {
            throw ValidationException::withMessages([
                'ubicacion_actual' => 'La ubicación actual está fuera del radio permitido para marcar llegada o cierre.',
            ]);
        }

        return round($distancia, 2);
    }

    public function distanciaEnMetros(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    protected function parseSeriesFromData(array $data): array
    {
        $raw = (string) ($data['numeros_serie'] ?? '');

        return collect(preg_split('/\r\n|\r|\n/', $raw))
            ->map(fn ($serie) => trim((string) $serie))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    protected function movimientoDisponibleParaCualquierTecnico(MovimientoLogistico $movimiento): bool
    {
        if (!is_null($movimiento->tecnico_id) || !in_array($movimiento->estado, ['pendiente', 'asignado'], true)) {
            return false;
        }

        return (
            $movimiento->tipo === 'recoleccion'
            && $movimiento->origen_tipo === 'inventario_programado'
        ) || (
            $movimiento->tipo === 'entrega'
            && $movimiento->origen_tipo === 'orden_servicio'
        );
    }

    protected function cantidadMovimientoDesdePayload(array $payload): float
    {
        if (($payload['tipo_control'] ?? null) === 'SERIE') {
            return (float) count($payload['seriales'] ?? []);
        }

        return (float) ($payload['cantidad_ingresada'] ?? 0);
    }
}
