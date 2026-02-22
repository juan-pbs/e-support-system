<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalidaInventario extends Model
{
    protected $table = 'detalle_orden_producto';
    protected $primaryKey = 'id_orden_producto';
    public $timestamps = true; // created_at = fecha salida

    protected $appends = ['series', 'tipo_control', 'fecha_salida', 'es_salida_manual'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'codigo_producto', 'codigo_producto');
    }

    public function ordenServicio()
    {
        return $this->belongsTo(OrdenServicio::class, 'id_orden_servicio', 'id_orden_servicio');
    }

    // Helpers
    public function getSeriesAttribute(): array
    {
        $desc = (string) ($this->descripcion ?? '');
        if (preg_match('/NS:\s*(.+)$/mi', $desc, $m)) {
            return array_values(array_filter(array_map('trim', explode(',', $m[1]))));
        }
        return [];
    }

    public function getTipoControlAttribute(): string
    {
        return count($this->series) ? 'piezas' : 'piezas_sin_serie';
    }

    public function getFechaSalidaAttribute()
    {
        return $this->created_at;
    }

    public function getEsSalidaManualAttribute(): bool
    {
        // Se considera “manual” si no está ligada a una OS
        return is_null($this->id_orden_servicio);
    }
}
