<?php

namespace App\Support;

use App\Models\MaintenanceSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MaintenanceSections
{
    public static function defaults(): array
    {
        return [
            'catalogo' => [
                'name' => 'Inventario / Catalogo',
                'paths' => ['catalogo*'],
                'message' => 'El catalogo esta en mantenimiento temporal.',
            ],
            'inventario' => [
                'name' => 'Bitacora de inventario',
                'paths' => ['inventario*'],
                'message' => 'La bitacora de inventario esta en mantenimiento temporal.',
            ],
            'cotizaciones' => [
                'name' => 'Cotizaciones',
                'paths' => ['cotizaciones*'],
                'message' => 'El modulo de cotizaciones esta en mantenimiento temporal.',
            ],
            'ordenes' => [
                'name' => 'Ordenes de servicio',
                'paths' => ['ordenes*'],
                'message' => 'Las ordenes de servicio estan en mantenimiento temporal.',
            ],
            'seguimiento' => [
                'name' => 'Seguimiento',
                'paths' => ['seguimiento*', 'api/seguimiento*'],
                'message' => 'El seguimiento de servicios esta en mantenimiento temporal.',
            ],
            'reportes' => [
                'name' => 'Reportes',
                'paths' => ['reportes*'],
                'message' => 'Los reportes estan en mantenimiento temporal.',
            ],
            'clientes' => [
                'name' => 'Clientes',
                'paths' => ['clientes*'],
                'message' => 'La administracion de clientes esta en mantenimiento temporal.',
            ],
            'empleados' => [
                'name' => 'Empleados',
                'paths' => ['empleados*'],
                'message' => 'La administracion de empleados esta en mantenimiento temporal.',
            ],
            'proveedores' => [
                'name' => 'Proveedores',
                'paths' => ['proveedores*'],
                'message' => 'La administracion de proveedores esta en mantenimiento temporal.',
            ],
            'tecnico' => [
                'name' => 'Panel tecnico',
                'paths' => ['tecnico*'],
                'message' => 'El panel tecnico esta en mantenimiento temporal.',
            ],
        ];
    }

    public static function ensureDefaults(): Collection
    {
        if (! Schema::hasTable('maintenance_sections')) {
            return collect();
        }

        foreach (self::defaults() as $key => $data) {
            MaintenanceSection::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'paths' => $data['paths'],
                    'message' => $data['message'],
                    'enabled' => false,
                ]
            );
        }

        return MaintenanceSection::query()
            ->orderBy('name')
            ->get();
    }
}
