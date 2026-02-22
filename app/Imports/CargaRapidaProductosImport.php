<?php

namespace App\Imports;

use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Inventario;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CargaRapidaProductosImport implements ToCollection, WithHeadingRow
{
    protected array $permitidos;
    protected int $ok = 0;
    protected int $omitidos = 0;
    protected array $rechazos = [];

    public function __construct(array $permitidos = [])
    {
        $this->permitidos = $permitidos;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $i => $r) {
            try {
                $row = collect($r)->map(fn($v) => is_string($v) ? trim($v) : $v);

                $emisor    = (string)($row['nombre_emisor'] ?? '');
                $rfc       = (string)($row['rfc_emisor'] ?? '');
                $serie     = (string)($row['serie'] ?? '');
                $folio     = (string)($row['folio'] ?? '');
                $fecha     = $row['▼_fecha_comprobante'] ?? $row['fecha_comprobante'] ?? null;
                $desc      = (string)($row['descripcion'] ?? '');
                $clavePS   = (string)($row['clave_prod/serv'] ?? '');
                $claveUni  = (string)($row['clave_unidad'] ?? '');
                $unidad    = (string)($row['unidad'] ?? '');
                $valorUnit = (float)($row['valor_unitario'] ?? 0);
                $importe   = (float)($row['importe'] ?? 0);
                $sku       = (string)($row['núm._identificación']
                                    ?? $row['num._identificacion']
                                    ?? $row['num._identificación']
                                    ?? '');
                $cantidad  = (float)($row['cantidad'] ?? 0);

                // Filtro por emisor (alias / permitidos)
                if (!$this->pasaFiltroEmisor($emisor)) {
                    $this->omitidos++;
                    continue;
                }

                // Datos mínimos obligatorios
                if ($cantidad <= 0 || $valorUnit <= 0 || empty($desc)) {
                    $this->rechazar($i, 'Datos incompletos');
                    continue;
                }

                // Clave única obligatoria
                if (empty($sku)) {
                    $this->rechazar($i, 'Sin Núm. Identificación (numero_parte)');
                    continue;
                }

                DB::transaction(function () use (
                    $emisor,$rfc,$serie,$folio,$fecha,
                    $desc,$clavePS,$claveUni,$unidad,
                    $valorUnit,$importe,$sku,$cantidad
                ) {
                    // Proveedor por RFC o nombre
                    $prov = Proveedor::firstOrCreate(
                        array_filter(['rfc' => $rfc ?: null]),
                        [
                            'nombre' => $emisor,
                            'alias'  => $this->aliasEmisor($emisor),
                        ]
                    );

                    if (!$prov->nombre) {
                        $prov->nombre = $emisor;
                        $prov->save();
                    }

                    // Producto por numero_parte (único)
                    $producto = Producto::firstOrCreate(
                        ['numero_parte' => $sku],
                        [
                            'nombre'              => Str::limit($desc, 120),
                            'unidad'              => $unidad ?: 'PIEZA',
                            'descripcion'         => $desc,
                            'categoria'           => null,
                            'activo'              => true,
                            'stock_total'         => 0,
                            'stock_paquetes'      => 0,
                            'stock_piezas_sueltas'=> 0,
                            'requiere_serie'      => false,
                        ]
                    );

                    // Entrada como piezas sin número de serie
                    Inventario::create([
                        'codigo_producto'       => $producto->getKey(),
                        'costo'                 => $valorUnit,
                        'precio'                => $valorUnit,
                        'tipo_control'          => 'piezas_sin_serie',
                        'cantidad_ingresada'    => $cantidad,
                        'piezas_por_paquete'    => null,
                        'fecha_entrada'         => $this->toDate($fecha),
                        'fecha_caducidad'       => null,
                        'clave_proveedor'       => $prov->getKey(),

                        'folio_proveedor'       => $folio ?: null,
                        'serie_comprobante'     => $serie ?: null,
                        'fecha_comprobante'     => $this->toDate($fecha),
                        'clave_unidad'          => $claveUni ?: null,
                        'clave_prodserv'        => $clavePS ?: null,
                        'numero_identificacion' => $sku,
                        'descripcion_factura'   => $desc,
                        'importe'               => $importe ?: round($valorUnit * $cantidad, 2),
                        'valor_unitario'        => $valorUnit,
                        'emisor'                => $emisor,
                    ]);

                    // Actualizar stock (en piezas)
                    $producto->increment('stock_total', (int)round($cantidad));
                    $producto->increment('stock_piezas_sueltas', (int)round($cantidad));
                });

                $this->ok++;

            } catch (\Throwable $e) {
                $this->rechazar($i, $e->getMessage());
            }
        }
    }

    private function normalizar($s)
    {
        $s = mb_strtoupper($s ?? '');
        $s = @iconv('UTF-8','ASCII//TRANSLIT', $s);
        return preg_replace('/[^A-Z0-9\s]/', '', $s);
    }

    private function aliasEmisor(string $emisor): ?string
    {
        $n = $this->normalizar($emisor);

        $map = [
            'INGRAM MICRO MEXICO'                 => 'INGRAM',
            'INGRAM'                              => 'INGRAM',
            'CT INTERNACIONAL'                    => 'CT',
            'CT'                                  => 'CT',
            'EXEL DEL NORTE'                      => 'EXEL',
            'EXEL'                                => 'EXEL',
            'SYSCOM'                              => 'SYSCOM',
            'SISTEMAS Y SERVICIOS DE COMUNICACION'=> 'SYSCOM',
            'COMERCIALIZADORA DEL VALOR AGREGADO' => 'CVA',
            'CVA'                                 => 'CVA',
            'C '                                  => 'CVA',
        ];

        foreach ($map as $needle => $alias) {
            if (str_contains($n, $this->normalizar($needle))) {
                return $alias;
            }
        }
        return null;
    }

    private function pasaFiltroEmisor(string $emisor): bool
    {
        if (empty($this->permitidos)) return true;
        $alias = $this->aliasEmisor($emisor);
        return $alias && in_array($alias, $this->permitidos, true);
    }

    private function toDate($val)
    {
        try {
            return \Carbon\Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function rechazar($rowIndex, $motivo)
    {
        $this->omitidos++;
        // +2 por encabezado típico (fila 1 headings)
        $this->rechazos[] = [
            'fila'   => $rowIndex + 2,
            'motivo' => $motivo,
        ];
    }

    public function resumen(): array
    {
        return [
            'importados' => $this->ok,
            'omitidos'   => $this->omitidos,
        ];
    }

    public function rechazados(): array
    {
        return $this->rechazos;
    }
}
