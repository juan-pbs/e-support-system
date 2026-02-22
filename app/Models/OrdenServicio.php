<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OrdenServicio extends Model
{
    protected $table = 'orden_servicio';
    protected $primaryKey = 'id_orden_servicio';
    public $incrementing = true;
    protected $keyType = 'int';

    public function getRouteKeyName()
    {
        return 'id_orden_servicio';
    }

    protected $fillable = [
        'id_cotizacion',
        'id_cliente',
        'id_tecnico',
        'fecha_orden',
        'estado',
        'prioridad',
        'fecha_finalizacion',
        'servicio',
        'descripcion_servicio',
        'descripcion',
        'precio',
        'costo_operativo',
        'precio_escrito',
        'materiales',
        'condiciones_generales',
        'firma_conformidad',
        'tipo_pago',
        'tipo_orden',
        'archivo_pdf',
        'autorizado_por',
        'moneda',
        'tasa_cambio',
        'impuestos',

        // ✅ NUEVO: total adicional base MXN
        'total_adicional_mxn',

        // ✅ NUEVO: anticipo (base MXN) + porcentaje
        'anticipo_mxn',
        'anticipo_porcentaje',

        // ===== Acta de conformidad =====
        'acta_pdf_path',
        'acta_pdf_hash',
        'acta_firmada_at',
        'acta_estado',
        'acta_data',

        // ===== Firmas dedicadas =====
        'firma_resp_path',
        'firma_emp_path',
        'firma_resp_data',
        'firma_emp_data',
    ];

    protected $casts = [
        'fecha_orden'           => 'date',
        'fecha_finalizacion'    => 'date',
        'precio'                => 'float',
        'costo_operativo'       => 'float',
        'impuestos'             => 'float',
        'tasa_cambio'           => 'float',
        'total_adicional_mxn'   => 'float',

        // ✅ Anticipo
        'anticipo_mxn'          => 'float',
        'anticipo_porcentaje'   => 'float',

        'acta_firmada_at'       => 'datetime',
        'acta_data'             => 'array',
        'firma_resp_data'       => 'string',
        'firma_emp_data'        => 'string',
    ];

    /* ============================
     |  Relaciones
     * ============================ */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class, 'id_cotizacion');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'clave_cliente');
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'id_tecnico');
    }

    public function tecnicos()
    {
        return $this->belongsToMany(User::class, 'orden_servicio_tecnico', 'id_orden_servicio', 'user_id')
            ->withTimestamps();
    }

    public function autorizadoPor()
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }

    public function seguimientos()
    {
        return $this->hasMany(SeguimientoServicio::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    public function productos()
    {
        return $this->hasMany(DetalleOrdenProducto::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    public function materialesExtras()
    {
        return $this->hasMany(OrdenMaterialExtra::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    public function imagenes()
    {
        return $this->hasMany(SeguimientoImagen::class, 'id_orden_servicio', 'id_orden_servicio')
            ->orderBy('orden');
    }

    /* ============================
     |  Mutators firmas
     * ============================ */
    public function setFirmaEmpDataAttribute($value): void
    {
        if (is_string($value) && str_starts_with($value, 'data:image')) {
            [$meta, $data] = explode(',', $value, 2);
            $this->attributes['firma_emp_data'] = $data ?? null;
        } else {
            $this->attributes['firma_emp_data'] = $value ?: null;
        }
    }

    public function setFirmaRespDataAttribute($value): void
    {
        if (is_string($value) && str_starts_with($value, 'data:image')) {
            [$meta, $data] = explode(',', $value, 2);
            $this->attributes['firma_resp_data'] = $data ?? null;
        } else {
            $this->attributes['firma_resp_data'] = $value ?: null;
        }
    }

    public function getFirmaEmpSrcAttribute(): ?string
    {
        $b64 = $this->attributes['firma_emp_data'] ?? null;
        if (!empty($b64)) return 'data:image/png;base64,' . $b64;

        $path = $this->attributes['firma_emp_path'] ?? null;
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getFirmaRespSrcAttribute(): ?string
    {
        $b64 = $this->attributes['firma_resp_data'] ?? null;
        if (!empty($b64)) return 'data:image/png;base64,' . $b64;

        $path = $this->attributes['firma_resp_path'] ?? null;
        return $path ? asset('storage/' . ltrim($path, '/')) : null;
    }

    public function getHasFirmaEmpAttribute(): bool
    {
        return !empty($this->attributes['firma_emp_data'] ?? null)
            || !empty($this->attributes['firma_emp_path'] ?? null);
    }

    public function getHasFirmaRespAttribute(): bool
    {
        return !empty($this->attributes['firma_resp_data'] ?? null)
            || !empty($this->attributes['firma_resp_path'] ?? null);
    }

    /* ============================
     |  Helpers / Accessors
     * ============================ */
    public function getTecnicosNombresAttribute(): string
    {
        return $this->tecnicos->pluck('name')->filter()->implode(', ');
    }

    public function getPrecioConMonedaAttribute()
    {
        return ($this->moneda === 'USD' ? 'USD $' : 'MXN $') . number_format((float)$this->precio, 2);
    }

    public function getUsaCreditoAttribute()
    {
        return $this->tipo_pago === 'credito_cliente';
    }

    public function getActaFirmadaAttribute(): bool
    {
        return ($this->acta_estado === 'firmada') && !is_null($this->acta_firmada_at);
    }

    /**
     * ✅ Total adicional en moneda de la orden (MXN o USD),
     * usando el campo almacenado total_adicional_mxn.
     */
    public function getTotalAdicionalAttribute(): float
    {
        $mxn = (float)($this->attributes['total_adicional_mxn'] ?? 0);

        $moneda = strtoupper((string)($this->attributes['moneda'] ?? 'MXN'));
        $tc     = (float)($this->attributes['tasa_cambio'] ?? 1);

        if ($moneda === 'USD') {
            return $tc > 0 ? round($mxn / $tc, 2) : round($mxn, 2);
        }

        return round($mxn, 2);
    }

    /**
     * ✅ Anticipo en moneda de la orden (MXN o USD),
     * usando el campo almacenado anticipo_mxn.
     */
    public function getAnticipoAttribute(): float
    {
        $mxn = (float)($this->attributes['anticipo_mxn'] ?? 0);

        $moneda = strtoupper((string)($this->attributes['moneda'] ?? 'MXN'));
        $tc     = (float)($this->attributes['tasa_cambio'] ?? 1);

        if ($moneda === 'USD') {
            return $tc > 0 ? round($mxn / $tc, 2) : round($mxn, 2);
        }

        return round($mxn, 2);
    }

    /**
     * ✅ Total final (base + operativo + impuestos + adicional)
     */
    public function getTotalFinalAttribute(): float
    {
        $base      = (float) ($this->precio ?? 0);
        $operativo = (float) ($this->costo_operativo ?? 0);
        $impuestos = (float) ($this->impuestos ?? 0);

        return round($base + $operativo + $impuestos + (float)$this->total_adicional, 2);
    }

    /**
     * ✅ Saldo pendiente = Total final - Anticipo (en moneda de la orden)
     */
    public function getSaldoPendienteAttribute(): float
    {
        return max(round(((float)$this->total_final - (float)$this->anticipo), 2), 0);
    }

    /**
     * ✅ Porcentaje calculado del anticipo (si no se guardó, lo calcula)
     */
    public function getAnticipoPctCalculadoAttribute(): float
    {
        $pct = $this->attributes['anticipo_porcentaje'] ?? null;
        if ($pct !== null && $pct !== '') return round((float)$pct, 2);

        $total = (float)$this->total_final;
        if ($total <= 0) return 0;

        return round(((float)$this->anticipo / $total) * 100, 2);
    }

    public function recalcularTotalAdicionalMxn(): float
    {
        $sum = (float) $this->materialesExtras()
            ->whereNotNull('precio_unitario')
            ->selectRaw('COALESCE(SUM(cantidad * precio_unitario), 0) as total')
            ->value('total');

        $this->total_adicional_mxn = $sum;
        $this->save();

        return $sum;
    }

    public function getMaterialesNoPrevistosResumenAttribute(): string
    {
        $nombres = $this->materialesExtras()->pluck('descripcion')->filter()->all();
        return count($nombres)
            ? implode(', ', array_slice($nombres, 0, 3)) . (count($nombres) > 3 ? '…' : '')
            : '-';
    }

    public function getStatusSeguimientoAttribute(): string
    {
        $estado = strtolower((string) $this->estado);
        if (in_array($estado, ['cancelado', 'cancelada'])) return 'cancelado';

        if (in_array($estado, ['finalizado', 'finalizada', 'completada'])) {
            return ($this->acta_estado === 'firmada') ? 'finalizado' : 'finalizado-sin-firmar';
        }

        return 'en-proceso';
    }

    public function getContratoFirmadoAttribute(): bool
    {
        return $this->acta_estado === 'firmada';
    }

    public function getTecnicoNombreCortoAttribute(): string
    {
        $n = trim($this->tecnicos_nombres ?? '');
        if ($n !== '') return $n;
        return optional($this->tecnico)->name ?? '—';
    }

    public function getComentarioRecienteAttribute(): string
    {
        $seg = $this->seguimientos()->latest('created_at')->first();
        return $seg?->observaciones ?? '';
    }

    public function getFolioAttribute(): string
    {
        $id = (int) ($this->attributes[$this->primaryKey] ?? 0);
        return 'ORD-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }
}
 