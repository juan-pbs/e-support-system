<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\ClienteDireccionLogistica;
use App\Models\DetalleOrdenProducto;
use App\Models\Inventario;
use App\Models\JornadaLogistica;
use App\Models\MovimientoLogistico;
use App\Models\OrdenServicio;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Services\Logistica\LogisticaService;
use App\Services\Ordenes\OrdenServicioService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class LogisticaDemoSeeder extends Seeder
{
    public function run(): void
    {
        /** @var OrdenServicioService $ordenes */
        $ordenes = app(OrdenServicioService::class);
        /** @var LogisticaService $logistica */
        $logistica = app(LogisticaService::class);

        [$gerente, $tecnico] = $this->seedUsuariosDemo();
        $proveedores = $this->seedProveedoresDemo();
        $clientes = $this->seedClientesDemo();
        $productos = $this->seedProductosDemo($proveedores);
        $this->seedInventarioDemo($productos, $proveedores, $ordenes);
        $jornada = $this->seedJornadaDemo($gerente);

        $this->seedOrdenEntregaDemo($clientes, $productos, $tecnico, $gerente, $jornada, $logistica, $ordenes);
        $this->seedRecoleccionDemo($productos, $proveedores, $tecnico, $gerente, $jornada, $logistica);
    }

    protected function seedUsuariosDemo(): array
    {
        $gerente = User::query()->where('email', 'gerencia.logistica.demo@example.com')->first();
        if (!$gerente) {
            $gerente = User::factory()->create([
                'name' => 'Gerencia Logistica Demo',
                'email' => 'gerencia.logistica.demo@example.com',
                'puesto' => 'gerente',
            ]);
        }

        $tecnico = User::query()->where('email', 'tecnico.logistica.demo@example.com')->first();
        if (!$tecnico) {
            $tecnico = User::factory()->create([
                'name' => 'Tecnico Logistica Demo',
                'email' => 'tecnico.logistica.demo@example.com',
                'puesto' => 'tecnico',
            ]);
        }

        return [$gerente, $tecnico];
    }

    protected function seedProveedoresDemo(): array
    {
        $rows = [
            [
                'rfc' => 'LDM010101Q10',
                'nombre' => 'Proveedor Demo Universidad',
                'alias' => 'Bodega Universidad',
                'direccion_logistica' => 'Av. Universidad 145, Santiago de Querétaro, Qro.',
                'direccion_logistica_place_id' => 'osm:demo-prov-1',
                'direccion_logistica_latitud' => 20.5932100,
                'direccion_logistica_longitud' => -100.3923400,
                'direccion_logistica_referencia' => 'Acceso de carga por lateral',
                'direccion_logistica_verificada_en_mapa' => true,
                'direccion_logistica_metodo' => 'autocomplete',
                'contacto' => 'Rogelio Almacen',
                'telefono' => '4428101101',
                'correo' => 'proveedor.universidad.demo@example.com',
            ],
            [
                'rfc' => 'LDM010101Q11',
                'nombre' => 'Proveedor Demo 5 de Febrero',
                'alias' => 'CEDIS 5 de Febrero',
                'direccion_logistica' => 'Av. 5 de Febrero 1307, Santiago de Querétaro, Qro.',
                'direccion_logistica_place_id' => 'osm:demo-prov-2',
                'direccion_logistica_latitud' => 20.6154500,
                'direccion_logistica_longitud' => -100.4065300,
                'direccion_logistica_referencia' => 'Portón gris junto a patio de maniobras',
                'direccion_logistica_verificada_en_mapa' => true,
                'direccion_logistica_metodo' => 'autocomplete',
                'contacto' => 'Martha Recepcion',
                'telefono' => '4428101102',
                'correo' => 'proveedor.5feb.demo@example.com',
            ],
            [
                'rfc' => 'LDM010101Q12',
                'nombre' => 'Proveedor Demo Benito Juárez',
                'alias' => 'Bodega Benito Juárez',
                'direccion_logistica' => 'Blvd. Benito Juárez 35, Industrial Benito Juárez, Santiago de Querétaro, Qro.',
                'direccion_logistica_place_id' => 'osm:demo-prov-3',
                'direccion_logistica_latitud' => 20.6139800,
                'direccion_logistica_longitud' => -100.4013200,
                'direccion_logistica_referencia' => 'Acceso de proveedores junto a caseta',
                'direccion_logistica_verificada_en_mapa' => true,
                'direccion_logistica_metodo' => 'autocomplete',
                'contacto' => 'Erika Almacen',
                'telefono' => '4428101103',
                'correo' => 'proveedor.benito.demo@example.com',
            ],
            [
                'rfc' => 'LDM010101Q13',
                'nombre' => 'Proveedor Demo Tlacote',
                'alias' => 'Patio Tlacote',
                'direccion_logistica' => 'Av. Tlacote 211, Desarrollo San Pablo, Santiago de Querétaro, Qro.',
                'direccion_logistica_place_id' => 'osm:demo-prov-4',
                'direccion_logistica_latitud' => 20.6062400,
                'direccion_logistica_longitud' => -100.4102800,
                'direccion_logistica_referencia' => 'Portón azul con área de maniobras',
                'direccion_logistica_verificada_en_mapa' => true,
                'direccion_logistica_metodo' => 'autocomplete',
                'contacto' => 'Luis Embarques',
                'telefono' => '4428101104',
                'correo' => 'proveedor.tlacote.demo@example.com',
            ],
            [
                'rfc' => 'LDM010101Q14',
                'nombre' => 'Proveedor Demo Jurica',
                'alias' => 'CEDIS Jurica',
                'direccion_logistica' => 'Calle 1 8, Parque Industrial Jurica, Santiago de Querétaro, Qro.',
                'direccion_logistica_place_id' => 'osm:demo-prov-5',
                'direccion_logistica_latitud' => 20.6411200,
                'direccion_logistica_longitud' => -100.4318500,
                'direccion_logistica_referencia' => 'Recepción de carga al fondo del andén',
                'direccion_logistica_verificada_en_mapa' => true,
                'direccion_logistica_metodo' => 'autocomplete',
                'contacto' => 'Nadia Recibo',
                'telefono' => '4428101105',
                'correo' => 'proveedor.jurica.demo@example.com',
            ],
        ];

        return collect($rows)->map(function (array $row) {
            $proveedor = Proveedor::query()->firstOrNew(['rfc' => $row['rfc']]);
            $proveedor->fill(array_merge($row, [
                'direccion' => $row['direccion_logistica'],
            ]));
            $proveedor->save();

            return $proveedor;
        })->keyBy('alias')->all();
    }

    protected function seedClientesDemo(): array
    {
        $rows = [
            [
                'codigo_cliente' => 'CLI-LOG-DEMO-01',
                'nombre' => 'Tecnologia Bajio Demo',
                'nombre_empresa' => 'Tecnologia Bajio Demo SA de CV',
                'direccion_fiscal' => 'Av. Zaragoza 48, Centro, Santiago de Querétaro, Qro.',
                'contacto' => 'Ana Ruta',
                'telefono' => '4429201001',
                'correo_electronico' => 'cliente.bajio.demo@example.com',
                'datos_fiscales' => 'TBD010101AAA',
                'ubicacion' => 'Santiago de Querétaro, Qro.',
                'direcciones' => [
                    [
                        'alias' => 'Oficina Centro',
                        'direccion_formateada' => 'Av. Zaragoza 48, Centro, Santiago de Querétaro, Qro.',
                        'place_id' => 'osm:demo-cli-1a',
                        'latitud' => 20.5906800,
                        'longitud' => -100.3900500,
                        'referencia' => 'Recepción frente a plaza principal',
                        'predeterminada' => true,
                    ],
                    [
                        'alias' => 'Bodega Carretas',
                        'direccion_formateada' => 'Av. Constituyentes 123 Oriente, Carretas, Santiago de Querétaro, Qro.',
                        'place_id' => 'osm:demo-cli-1b',
                        'latitud' => 20.5887900,
                        'longitud' => -100.3844500,
                        'referencia' => 'Acceso por rampa trasera',
                        'predeterminada' => false,
                    ],
                ],
            ],
            [
                'codigo_cliente' => 'CLI-LOG-DEMO-02',
                'nombre' => 'Infraestructura Juriquilla Demo',
                'nombre_empresa' => 'Infraestructura Juriquilla Demo SA',
                'direccion_fiscal' => 'Blvd. Villas del Mesón 56, Juriquilla, Santiago de Querétaro, Qro.',
                'contacto' => 'Marco Obra',
                'telefono' => '4429201002',
                'correo_electronico' => 'cliente.juriquilla.demo@example.com',
                'datos_fiscales' => 'IJD010101AAB',
                'ubicacion' => 'Juriquilla, Santiago de Querétaro, Qro.',
                'direcciones' => [
                    [
                        'alias' => 'Corporativo Juriquilla',
                        'direccion_formateada' => 'Blvd. Villas del Mesón 56, Juriquilla, Santiago de Querétaro, Qro.',
                        'place_id' => 'osm:demo-cli-2a',
                        'latitud' => 20.7011500,
                        'longitud' => -100.4477100,
                        'referencia' => 'Lobby principal con caseta',
                        'predeterminada' => true,
                    ],
                    [
                        'alias' => 'Obra Antea',
                        'direccion_formateada' => 'Carretera San Luis Potosí 12401, Jurica, Santiago de Querétaro, Qro.',
                        'place_id' => 'osm:demo-cli-2b',
                        'latitud' => 20.6485600,
                        'longitud' => -100.4339000,
                        'referencia' => 'Ingreso por estacionamiento de proveedores',
                        'predeterminada' => false,
                    ],
                ],
            ],
        ];

        return collect($rows)->map(function (array $row) {
            $cliente = Cliente::query()->firstOrNew(['codigo_cliente' => $row['codigo_cliente']]);
            $cliente->fill([
                'codigo_cliente' => $row['codigo_cliente'],
                'nombre' => $row['nombre'],
                'nombre_empresa' => $row['nombre_empresa'],
                'direccion_fiscal' => $row['direccion_fiscal'],
                'contacto' => $row['contacto'],
                'telefono' => $row['telefono'],
                'correo_electronico' => $row['correo_electronico'],
                'datos_fiscales' => $row['datos_fiscales'],
                'ubicacion' => $row['ubicacion'],
            ]);
            $cliente->save();

            foreach ($row['direcciones'] as $direccion) {
                ClienteDireccionLogistica::query()->updateOrCreate(
                    [
                        'clave_cliente' => $cliente->clave_cliente,
                        'alias' => $direccion['alias'],
                    ],
                    [
                        'direccion_formateada' => $direccion['direccion_formateada'],
                        'place_id' => $direccion['place_id'],
                        'latitud' => $direccion['latitud'],
                        'longitud' => $direccion['longitud'],
                        'referencia' => $direccion['referencia'],
                        'activa' => true,
                        'predeterminada' => (bool) $direccion['predeterminada'],
                        'verificada_en_mapa' => true,
                        'metodo_verificacion' => 'autocomplete',
                    ]
                );
            }

            $principal = $cliente->direccionesLogisticas()->where('predeterminada', true)->first();
            $cliente->ubicacion = $principal?->direccion_formateada ?: $row['ubicacion'];
            $cliente->save();

            return $cliente->fresh('direccionesLogisticas');
        })->keyBy('codigo_cliente')->all();
    }

    protected function seedProductosDemo(array $proveedores): array
    {
        $rows = [
            [
                'numero_parte' => 'LOG-DEMO-CAM-4MP',
                'nombre' => 'Camara IP 4MP Demo',
                'categoria' => 'Logistica',
                'descripcion' => 'Camara IP para pruebas de entrega logística.',
                'unidad' => 'pieza',
                'clave_prodserv' => '43222600',
                'proveedores' => ['Bodega Universidad'],
            ],
            [
                'numero_parte' => 'LOG-DEMO-SW-8POE',
                'nombre' => 'Switch PoE 8 Puertos Demo',
                'categoria' => 'Logistica',
                'descripcion' => 'Switch PoE para pruebas de entrega logística.',
                'unidad' => 'pieza',
                'clave_prodserv' => '43222609',
                'proveedores' => ['Bodega Universidad', 'CEDIS 5 de Febrero'],
            ],
            [
                'numero_parte' => 'LOG-DEMO-NVR-8',
                'nombre' => 'NVR 8 Canales Demo',
                'categoria' => 'Logistica',
                'descripcion' => 'Grabador NVR para demostración de logística.',
                'unidad' => 'pieza',
                'clave_prodserv' => '45121504',
                'proveedores' => ['CEDIS 5 de Febrero'],
            ],
            [
                'numero_parte' => 'LOG-DEMO-CAT6',
                'nombre' => 'Cable UTP Cat6 Demo',
                'categoria' => 'Logistica',
                'descripcion' => 'Cable UTP para escenarios de inventario demo.',
                'unidad' => 'pieza',
                'clave_prodserv' => '26121609',
                'proveedores' => ['CEDIS 5 de Febrero'],
            ],
            [
                'numero_parte' => 'LOG-DEMO-PSU-12V',
                'nombre' => 'Fuente 12V 10A Demo',
                'categoria' => 'Logistica',
                'descripcion' => 'Fuente de poder para pruebas de recolección programada.',
                'unidad' => 'pieza',
                'clave_prodserv' => '39121004',
                'proveedores' => ['CEDIS 5 de Febrero', 'Bodega Benito Juárez', 'Patio Tlacote', 'CEDIS Jurica'],
            ],
        ];

        return collect($rows)->map(function (array $row) use ($proveedores) {
            $producto = Producto::query()->firstOrNew(['numero_parte' => $row['numero_parte']]);
            $producto->fill([
                'nombre' => $row['nombre'],
                'numero_parte' => $row['numero_parte'],
                'categoria' => $row['categoria'],
                'clave_prodserv' => $row['clave_prodserv'],
                'unidad' => $row['unidad'],
                'stock_seguridad' => 0,
                'descripcion' => $row['descripcion'],
                'activo' => true,
                'stock_total' => $producto->stock_total ?? 0,
                'stock_paquetes' => $producto->stock_paquetes ?? 0,
                'stock_piezas_sueltas' => $producto->stock_piezas_sueltas ?? 0,
            ]);
            $producto->save();

            $proveedoresIds = collect($row['proveedores'])
                ->map(fn ($alias) => $proveedores[$alias]->clave_proveedor ?? null)
                ->filter()
                ->values()
                ->all();

            if (!empty($proveedoresIds)) {
                $producto->proveedores()->syncWithoutDetaching($proveedoresIds);
            }

            return $producto;
        })->keyBy('numero_parte')->all();
    }

    protected function seedInventarioDemo(array $productos, array $proveedores, OrdenServicioService $ordenes): void
    {
        $items = [
            [
                'producto' => 'LOG-DEMO-CAM-4MP',
                'proveedor' => 'Bodega Universidad',
                'costo' => 950,
                'precio' => 1450,
                'cantidad_ingresada' => 6,
            ],
            [
                'producto' => 'LOG-DEMO-SW-8POE',
                'proveedor' => 'Bodega Universidad',
                'costo' => 680,
                'precio' => 1090,
                'cantidad_ingresada' => 4,
            ],
            [
                'producto' => 'LOG-DEMO-NVR-8',
                'proveedor' => 'CEDIS 5 de Febrero',
                'costo' => 2100,
                'precio' => 2950,
                'cantidad_ingresada' => 2,
            ],
            [
                'producto' => 'LOG-DEMO-CAT6',
                'proveedor' => 'CEDIS 5 de Febrero',
                'costo' => 95,
                'precio' => 160,
                'cantidad_ingresada' => 20,
            ],
        ];

        foreach ($items as $item) {
            $producto = $productos[$item['producto']];
            $proveedor = $proveedores[$item['proveedor']];

            $exists = Inventario::query()
                ->where('codigo_producto', $producto->codigo_producto)
                ->where('clave_proveedor', $proveedor->clave_proveedor)
                ->where('tipo_control', 'PIEZAS')
                ->where('forma_ingreso', 'recibido_almacen')
                ->exists();

            if (!$exists) {
                Inventario::query()->create([
                    'codigo_producto' => $producto->codigo_producto,
                    'clave_proveedor' => $proveedor->clave_proveedor,
                    'costo' => $item['costo'],
                    'precio' => $item['precio'],
                    'tipo_control' => 'PIEZAS',
                    'cantidad_ingresada' => $item['cantidad_ingresada'],
                    'piezas_por_paquete' => null,
                    'paquetes_restantes' => 0,
                    'piezas_sueltas' => $item['cantidad_ingresada'],
                    'numero_serie' => null,
                    'fecha_entrada' => Carbon::today()->toDateString(),
                    'hora_entrada' => Carbon::now()->format('H:i:s'),
                    'forma_ingreso' => 'recibido_almacen',
                    'estado_recepcion' => 'recibido',
                    'movimiento_logistico_id' => null,
                ]);
            }

            $ordenes->refreshProductStockTotals($producto->codigo_producto);
        }
    }

    protected function seedJornadaDemo(User $gerente): JornadaLogistica
    {
        $jornada = JornadaLogistica::query()->firstOrNew([
            'folio' => 'JL-DEMO-001',
        ]);

        $jornada->fill([
            'nombre' => 'Ruta demo logística',
            'fecha' => Carbon::today()->toDateString(),
            'estado' => 'abierta',
            'created_by' => $jornada->created_by ?: $gerente->id,
            'opened_at' => $jornada->opened_at ?: Carbon::now(),
            'closed_by' => null,
            'closed_at' => null,
            'observaciones' => 'Jornada sembrada para validar entregas y recolecciones.',
        ]);
        $jornada->save();

        return $jornada;
    }

    protected function seedOrdenEntregaDemo(
        array $clientes,
        array $productos,
        User $tecnico,
        User $gerente,
        JornadaLogistica $jornada,
        LogisticaService $logistica,
        OrdenServicioService $ordenes
    ): void {
        $cliente = $clientes['CLI-LOG-DEMO-01'];
        $direccion = $cliente->direccionesLogisticas->firstWhere('predeterminada', true) ?? $cliente->direccionesLogisticas->first();

        $orden = OrdenServicio::query()->firstOrCreate(
            [
                'servicio' => 'Entrega demo logística',
                'id_cliente' => $cliente->clave_cliente,
            ],
            [
                'id_cotizacion' => null,
                'cliente_direccion_id' => $direccion?->id,
                'id_tecnico' => $tecnico->id,
                'requiere_logistica' => true,
                'fecha_orden' => Carbon::today()->toDateString(),
                'estado' => 'Pendiente',
                'prioridad' => 'Media',
                'descripcion_servicio' => 'Entrega demo sembrada para pruebas de logística.',
                'descripcion' => 'Orden demo ligada al módulo de logística.',
                'precio' => 0,
                'costo_operativo' => 0,
                'precio_escrito' => 'CERO PESOS 00/100 M.N.',
                'materiales' => null,
                'condiciones_generales' => 'Generada automáticamente para pruebas.',
                'tipo_pago' => 'efectivo',
                'facturado' => false,
                'tipo_orden' => 'compra',
                'archivo_pdf' => null,
                'autorizado_por' => $gerente->id,
                'moneda' => 'MXN',
                'tasa_cambio' => 1,
                'impuestos' => 0,
                'total_adicional_mxn' => 0,
                'anticipo_mxn' => 0,
                'anticipo_porcentaje' => 0,
            ]
        );

        $orden->cliente_direccion_id = $direccion?->id;
        $orden->id_tecnico = $tecnico->id;
        $orden->requiere_logistica = true;
        $orden->tipo_orden = 'compra';
        $orden->estado = 'Pendiente';
        $orden->prioridad = 'Media';
        $orden->fecha_orden = Carbon::today()->toDateString();
        $orden->autorizado_por = $gerente->id;
        $orden->save();

        $orden->tecnicos()->sync([$tecnico->id]);

        $detalles = [
            [
                'producto' => $productos['LOG-DEMO-CAM-4MP'],
                'cantidad' => 1,
                'precio' => 1450,
                'descripcion' => 'Entrega de cámara IP para prueba logística.',
            ],
            [
                'producto' => $productos['LOG-DEMO-SW-8POE'],
                'cantidad' => 1,
                'precio' => 1090,
                'descripcion' => 'Entrega de switch PoE para prueba logística.',
            ],
        ];

        $totalMaterial = 0;
        $orden->productos()->delete();

        foreach ($detalles as $detalle) {
            $producto = $detalle['producto'];
            $lineaTotal = round($detalle['cantidad'] * $detalle['precio'], 2);
            $totalMaterial += $lineaTotal;

            $payload = [
                'id_orden_servicio' => $orden->id_orden_servicio,
                'codigo_producto' => $producto->codigo_producto,
                'nombre_producto' => $producto->nombre,
                'cantidad' => $detalle['cantidad'],
                'precio_unitario' => $detalle['precio'],
                'total' => $lineaTotal,
            ];

            if (Schema::hasColumn('detalle_orden_producto', 'descripcion_item')) {
                $payload['descripcion_item'] = $detalle['descripcion'];
            } elseif (Schema::hasColumn('detalle_orden_producto', 'descripcion')) {
                $payload['descripcion'] = $detalle['descripcion'];
            }

            if (Schema::hasColumn('detalle_orden_producto', 'unidad')) {
                $payload['unidad'] = $producto->unidad;
            }

            DetalleOrdenProducto::query()->create($payload);

            $ordenes->refreshProductStockTotals($producto->codigo_producto);
        }

        $orden->precio = 0;
        $orden->costo_operativo = 0;
        $orden->impuestos = round($totalMaterial * 0.16, 2);
        $orden->precio_escrito = 'DOS MIL NOVECIENTOS TRES PESOS 60/100 M.N.';
        $orden->save();

        $movimiento = $logistica->syncEntregaDesdeOrden(
            $orden->fresh(['cliente.direccionesLogisticas', 'direccionCliente', 'productos', 'tecnicos'])
        );

        if ($movimiento) {
            $movimiento->jornada_logistica_id = $jornada->id;
            $movimiento->tecnico_id = $tecnico->id;
            $movimiento->save();
        }
    }

    protected function seedRecoleccionDemo(
        array $productos,
        array $proveedores,
        User $tecnico,
        User $gerente,
        JornadaLogistica $jornada,
        LogisticaService $logistica
    ): void {
        $producto = $productos['LOG-DEMO-PSU-12V'];
        $recolecciones = [
            [
                'proveedor' => 'CEDIS 5 de Febrero',
                'cantidad' => 5,
                'hora' => '12:00',
                'costo' => 180,
                'precio' => 310,
                'nota' => 'Recolección demo principal sembrada automáticamente.',
            ],
            [
                'proveedor' => 'Bodega Benito Juárez',
                'cantidad' => 3,
                'hora' => '12:20',
                'costo' => 182,
                'precio' => 315,
                'nota' => 'Recolección demo cercana 1 para ruta libre.',
            ],
            [
                'proveedor' => 'Patio Tlacote',
                'cantidad' => 4,
                'hora' => '12:40',
                'costo' => 176,
                'precio' => 305,
                'nota' => 'Recolección demo cercana 2 para ruta libre.',
            ],
            [
                'proveedor' => 'CEDIS Jurica',
                'cantidad' => 2,
                'hora' => '13:10',
                'costo' => 190,
                'precio' => 320,
                'nota' => 'Recolección demo adicional para extender la ruta.',
            ],
        ];

        foreach ($recolecciones as $index => $config) {
            $proveedor = $proveedores[$config['proveedor']];
            $existing = MovimientoLogistico::query()
                ->where('tipo', 'recoleccion')
                ->where('clave_proveedor', $proveedor->clave_proveedor)
                ->whereDate('fecha_programada', Carbon::today()->toDateString())
                ->where('origen_tipo', 'inventario_programado')
                ->latest('id')
                ->first();

            if ($existing) {
                if (in_array($existing->estado, ['pendiente', 'asignado'], true)) {
                    $existing->tecnico_id = null;
                    $existing->jornada_logistica_id = $jornada->id;
                    $existing->hora_programada = $config['hora'];
                    $existing->observaciones = $config['nota'];
                    $existing->payload = array_merge($existing->payload ?? [], [
                        'demo_seed' => true,
                        'demo_batch' => 'recolecciones_cercanas',
                        'demo_sort' => $index + 1,
                    ]);
                    $existing->save();
                }
                continue;
            }

            $movimiento = $logistica->programarRecoleccionInventario([
                'codigo_producto' => $producto->codigo_producto,
                'clave_proveedor' => $proveedor->clave_proveedor,
                'costo' => $config['costo'],
                'precio' => $config['precio'],
                'tipo_control' => 'PIEZAS',
                'cantidad_ingresada' => $config['cantidad'],
                'forma_ingreso' => 'recoleccion_programada',
                'jornada_logistica_id' => $jornada->id,
                'fecha_programada' => Carbon::today()->toDateString(),
                'hora_programada' => $config['hora'],
                'observaciones_logistica' => $config['nota'],
            ], $gerente);

            $movimiento->payload = array_merge($movimiento->payload ?? [], [
                'demo_seed' => true,
                'demo_batch' => 'recolecciones_cercanas',
                'demo_sort' => $index + 1,
            ]);
            $movimiento->save();
        }
    }
}
