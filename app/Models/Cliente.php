<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    use HasFactory;

    protected $table = 'cliente';
    protected $primaryKey = 'clave_cliente';
    public $incrementing = true; // seguimos usando PK autoincrementable
    protected $keyType = 'int';
    public $timestamps = true;

    protected $fillable = [
        'codigo_cliente', // ✅ NUEVO (manual)
        'nombre',
        'nombre_empresa',
        'direccion_fiscal',
        'contacto',
        'telefono',
        'contacto_adicional',
        'correo_electronico',
        'datos_fiscales',
        'ubicacion',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Obtener la dirección completa del cliente.
     */
    public function getDireccionCompletaAttribute(): string
    {
        return "{$this->direccion_fiscal}" . ($this->ubicacion ? " ({$this->ubicacion})" : '');
    }

    /**
     * Obtener la información de contacto principal del cliente.
     */
    public function getContactoPrincipalAttribute(): string
    {
        return "Tel: {$this->telefono} | Email: {$this->correo_electronico}";
    }

    /**
     * Scope para buscar clientes por nombre o empresa (y por código).
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('nombre', 'like', "%{$search}%")
              ->orWhere('nombre_empresa', 'like', "%{$search}%")
              ->orWhere('codigo_cliente', 'like', "%{$search}%"); // ✅ NUEVO
        });
    }

    /**
     * Relación con las cotizaciones
     */
    public function cotizaciones()
    {
        return $this->hasMany(Cotizacion::class, 'registro_cliente', 'clave_cliente');
    }

    /**
     * Relación uno a uno con el crédito del cliente
     */
    public function creditoCliente()
    {
        return $this->hasOne(CreditoCliente::class, 'clave_cliente', 'clave_cliente');
    }

    public function direccionesLogisticas()
    {
        return $this->hasMany(ClienteDireccionLogistica::class, 'clave_cliente', 'clave_cliente')
            ->orderByDesc('predeterminada')
            ->orderBy('alias');
    }

    public function direccionLogisticaPrincipal()
    {
        return $this->hasOne(ClienteDireccionLogistica::class, 'clave_cliente', 'clave_cliente')
            ->where('predeterminada', true);
    }

    /**
     * Relación con pagos de crédito
     */
    public function pagos()
    {
        return $this->hasMany(PagoCredito::class, 'clave_cliente', 'clave_cliente');
    }

    public function getDireccionLogisticaResumenAttribute(): ?string
    {
        $direccion = $this->relationLoaded('direccionesLogisticas')
            ? $this->direccionesLogisticas->firstWhere('predeterminada', true) ?? $this->direccionesLogisticas->first()
            : $this->direccionesLogisticas()->where('predeterminada', true)->first() ?? $this->direccionesLogisticas()->first();

        return $direccion?->direccion_formateada;
    }
}
