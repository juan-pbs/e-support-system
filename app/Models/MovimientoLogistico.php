<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoLogistico extends Model
{
    protected $table = 'movimientos_logisticos';

    protected $fillable = [
        'jornada_logistica_id',
        'orden_servicio_id',
        'clave_cliente',
        'cliente_direccion_id',
        'clave_proveedor',
        'tecnico_id',
        'tipo',
        'origen_tipo',
        'origen_id',
        'contacto',
        'telefono',
        'alias_direccion',
        'direccion_formateada',
        'place_id',
        'latitud',
        'longitud',
        'referencia',
        'estado',
        'fecha_programada',
        'hora_programada',
        'fecha_inicio',
        'fecha_llegada',
        'fecha_cierre',
        'llegada_latitud',
        'llegada_longitud',
        'cierre_latitud',
        'cierre_longitud',
        'radio_validacion_metros',
        'distancia_metros',
        'observaciones',
        'incidencia_descripcion',
        'recepcion_confirmada_at',
        'recepcion_confirmada_por',
        'payload',
    ];

    protected $casts = [
        'latitud' => 'float',
        'longitud' => 'float',
        'llegada_latitud' => 'float',
        'llegada_longitud' => 'float',
        'cierre_latitud' => 'float',
        'cierre_longitud' => 'float',
        'distancia_metros' => 'float',
        'radio_validacion_metros' => 'integer',
        'fecha_programada' => 'date',
        'hora_programada' => 'string',
        'fecha_inicio' => 'datetime',
        'fecha_llegada' => 'datetime',
        'fecha_cierre' => 'datetime',
        'recepcion_confirmada_at' => 'datetime',
        'payload' => 'array',
    ];

    public function jornada()
    {
        return $this->belongsTo(JornadaLogistica::class, 'jornada_logistica_id');
    }

    public function ordenServicio()
    {
        return $this->belongsTo(OrdenServicio::class, 'orden_servicio_id', 'id_orden_servicio');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'clave_cliente', 'clave_cliente');
    }

    public function direccionCliente()
    {
        return $this->belongsTo(ClienteDireccionLogistica::class, 'cliente_direccion_id');
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'clave_proveedor', 'clave_proveedor');
    }

    public function tecnico()
    {
        return $this->belongsTo(User::class, 'tecnico_id');
    }

    public function recepcionConfirmadaPor()
    {
        return $this->belongsTo(User::class, 'recepcion_confirmada_por');
    }

    public function detalles()
    {
        return $this->hasMany(MovimientoLogisticoDetalle::class, 'movimiento_logistico_id');
    }

    public function evidencias()
    {
        return $this->hasMany(MovimientoLogisticoEvidencia::class, 'movimiento_logistico_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return $this->tipo === 'recoleccion' ? 'Recolección' : 'Entrega';
    }

    public function getEstadoLabelAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->estado));
    }
}
