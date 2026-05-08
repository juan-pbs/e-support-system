<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cotizacion extends Model
{
    use HasFactory;

    protected $table = 'cotizaciones';
    protected $primaryKey = 'id_cotizacion';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'fecha',
        'vigencia',
        'moneda',
        'tipo_solicitud',
        'registro_cliente',
        'descripcion',
        'costo_operativo',
        'iva',
        'total',
        'cantidad_escrita',
        'observaciones_pdf',
        'condiciones_pago',
        'tiempo_entrega',
        'archivo_pdf',
        'tasa_cambio',

        // snapshot firma
        'firmante_nombre',
        'firmante_puesto',
        'firmante_empresa',
        'signature_image',

        // nuevos campos
        'edit_count',
        'last_edited_at',
        'process_count',
        'last_processed_at',
        'estado_cotizacion',
    ];

    protected $casts = [
        'fecha'             => 'datetime',
        'vigencia'          => 'datetime',
        'costo_operativo'   => 'float',
        'iva'               => 'float',
        'total'             => 'float',
        'last_edited_at'    => 'datetime',
        'last_processed_at' => 'datetime',
    ];

    /* Relaciones */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'registro_cliente', 'clave_cliente');
    }

    public function productos()
    {
        return $this->hasMany(DetalleCotizacionProducto::class, 'id_cotizacion', 'id_cotizacion');
    }

    public function servicio()
    {
        return $this->hasOne(CotizacionServicio::class, 'id_cotizacion', 'id_cotizacion');
    }

    public function ordenServicio()
    {
         return $this->hasOne(OrdenServicio::class, 'id_cotizacion', 'id_cotizacion');
     }

    public function getFolioAttribute(): string
    {
        return $this->buildNumeroCotizacion();
    }

    public function getNumeroCotizacionAttribute(): string
    {
        return $this->buildNumeroCotizacion();
    }

    protected function buildNumeroCotizacion(): string
    {
        $id = $this->attributes[$this->primaryKey] ?? null;
        $id = $id !== null && $id !== '' ? (string) $id : '';

        $codigoCliente = trim((string) optional($this->cliente)->codigo_cliente);

        if ($codigoCliente === '' && !empty($this->registro_cliente)) {
            $codigoCliente = trim((string) $this->cliente()->value('codigo_cliente'));
        }

        if ($codigoCliente !== '' && $id !== '') {
            return $codigoCliente . ' ' . $id;
        }

        if ($id !== '') {
            return 'SET-' . $id;
        }

        return 'SET-S/N';
    }
}
