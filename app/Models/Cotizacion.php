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
}
