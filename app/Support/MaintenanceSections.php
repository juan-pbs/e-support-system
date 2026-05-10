<?php

namespace App\Support;

use App\Models\MaintenanceSection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class MaintenanceSections
{
    public static function groupLabels(): array
    {
        return [
            'catalogo' => 'Inventario / Catalogo',
            'inventario' => 'Bitacora de inventario',
            'cotizaciones' => 'Cotizaciones',
            'ordenes' => 'Ordenes de servicio',
            'seguimiento' => 'Seguimiento',
            'reportes' => 'Reportes',
            'clientes' => 'Clientes',
            'empleados' => 'Empleados',
            'proveedores' => 'Proveedores',
            'tecnico' => 'Panel tecnico',
            'logistica' => 'Logistica',
        ];
    }

    public static function defaults(): array
    {
        return [
            'catalogo' => [
                'name' => 'Modulo completo',
                'paths' => ['catalogo*'],
                'message' => 'El catalogo esta en mantenimiento temporal.',
            ],
            'catalogo.crear' => [
                'name' => 'Crear productos',
                'paths' => ['route:producto.crear', 'route:producto.guardar', 'route:ordenes.productos.store-rapido'],
                'message' => 'La creacion de productos esta en mantenimiento temporal.',
            ],
            'catalogo.editar' => [
                'name' => 'Editar productos',
                'paths' => ['route:producto.editar', 'route:producto.actualizar', 'route:producto.desactivar', 'route:producto.activar'],
                'message' => 'La edicion de productos esta en mantenimiento temporal.',
            ],
            'catalogo.eliminar' => [
                'name' => 'Eliminar y restaurar productos',
                'paths' => ['route:producto.eliminar', 'route:producto.restaurar', 'route:catalogo.productos.bulk'],
                'message' => 'La eliminacion o restauracion de productos esta en mantenimiento temporal.',
            ],
            'catalogo.carga_rapida' => [
                'name' => 'Carga rapida y plantillas',
                'paths' => ['route:catalogo.carga_rapida.*', 'route:catalogo.importar', 'route:catalogo.plantilla', 'route:cargaRapidaProd.*'],
                'message' => 'La carga rapida del catalogo esta en mantenimiento temporal.',
            ],
            'catalogo.categorias' => [
                'name' => 'Categorias',
                'paths' => ['route:catalogo.categorias.*'],
                'message' => 'La administracion de categorias esta en mantenimiento temporal.',
            ],
            'inventario' => [
                'name' => 'Modulo completo',
                'paths' => ['inventario*'],
                'message' => 'La bitacora de inventario esta en mantenimiento temporal.',
            ],
            'inventario.entrada' => [
                'name' => 'Registrar entradas',
                'paths' => ['route:entrada*', 'route:inventario.entrada'],
                'message' => 'El registro de entradas esta en mantenimiento temporal.',
            ],
            'inventario.editar' => [
                'name' => 'Editar entradas',
                'paths' => ['route:inventario.editar', 'route:inventario.actualizar'],
                'message' => 'La edicion de inventario esta en mantenimiento temporal.',
            ],
            'inventario.eliminar' => [
                'name' => 'Eliminar inventario',
                'paths' => ['route:inventario.eliminar*'],
                'message' => 'La eliminacion de inventario esta en mantenimiento temporal.',
            ],
            'inventario.salidas' => [
                'name' => 'Salidas de inventario',
                'paths' => ['route:inventario.salidas*'],
                'message' => 'Las salidas de inventario estan en mantenimiento temporal.',
            ],
            'inventario.carga_rapida' => [
                'name' => 'Carga rapida',
                'paths' => ['route:inventario.carga_rapida.*', 'route:cargaRapidaProd.*'],
                'message' => 'La carga rapida de inventario esta en mantenimiento temporal.',
            ],
            'cotizaciones' => [
                'name' => 'Modulo completo',
                'paths' => ['cotizaciones*'],
                'message' => 'El modulo de cotizaciones esta en mantenimiento temporal.',
            ],
            'cotizaciones.crear' => [
                'name' => 'Crear cotizaciones',
                'paths' => ['route:cotizaciones.crear', 'route:cotizaciones.guardar'],
                'message' => 'La creacion de cotizaciones esta en mantenimiento temporal.',
            ],
            'cotizaciones.editar' => [
                'name' => 'Editar cotizaciones',
                'paths' => ['route:cotizaciones.editar', 'route:cotizaciones.actualizar'],
                'message' => 'La edicion de cotizaciones esta en mantenimiento temporal.',
            ],
            'cotizaciones.pdf' => [
                'name' => 'PDF y previsualizacion',
                'paths' => ['route:cotizaciones.preview', 'route:cotizaciones.verPDF', 'route:cotizaciones.descargarPDF'],
                'message' => 'La generacion de PDF de cotizaciones esta en mantenimiento temporal.',
            ],
            'cotizaciones.correo' => [
                'name' => 'Envio por correo',
                'paths' => ['route:cotizaciones.enviarCorreo'],
                'message' => 'El envio de cotizaciones por correo esta en mantenimiento temporal.',
            ],
            'cotizaciones.procesar' => [
                'name' => 'Procesar a orden',
                'paths' => ['route:cotizaciones.procesar'],
                'message' => 'El procesamiento de cotizaciones esta en mantenimiento temporal.',
            ],
            'cotizaciones.eliminar' => [
                'name' => 'Eliminar cotizaciones',
                'paths' => ['route:cotizaciones.eliminar'],
                'message' => 'La eliminacion de cotizaciones esta en mantenimiento temporal.',
            ],
            'ordenes' => [
                'name' => 'Modulo completo',
                'paths' => ['ordenes*'],
                'message' => 'Las ordenes de servicio estan en mantenimiento temporal.',
            ],
            'ordenes.crear' => [
                'name' => 'Crear ordenes',
                'paths' => ['route:ordenes.create', 'route:ordenes.store', 'route:ordenes.crearDesdeCotizacion', 'route:ordenes.guardarDesdeCotizacion'],
                'message' => 'La creacion de ordenes esta en mantenimiento temporal.',
            ],
            'ordenes.editar' => [
                'name' => 'Editar ordenes',
                'paths' => ['route:ordenes.edit', 'route:ordenes.update', 'route:ordenes.asignar*'],
                'message' => 'La edicion de ordenes esta en mantenimiento temporal.',
            ],
            'ordenes.pdf' => [
                'name' => 'PDF y previsualizacion',
                'paths' => ['route:ordenes.preview', 'route:ordenes.pdf'],
                'message' => 'La generacion de PDF de ordenes esta en mantenimiento temporal.',
            ],
            'ordenes.correo' => [
                'name' => 'Envio por correo',
                'paths' => ['route:ordenes.enviarCorreo'],
                'message' => 'El envio de ordenes por correo esta en mantenimiento temporal.',
            ],
            'ordenes.facturacion' => [
                'name' => 'Facturacion',
                'paths' => ['route:ordenes.facturacion.update'],
                'message' => 'La actualizacion de facturacion esta en mantenimiento temporal.',
            ],
            'ordenes.acta' => [
                'name' => 'Actas de conformidad',
                'paths' => ['route:ordenes.acta.*'],
                'message' => 'Las actas de conformidad estan en mantenimiento temporal.',
            ],
            'ordenes.acta.correo' => [
                'name' => 'Envio de actas por correo',
                'paths' => ['route:ordenes.acta.enviarCorreo'],
                'message' => 'El envio de actas de conformidad por correo esta en mantenimiento temporal.',
            ],
            'ordenes.seguimiento' => [
                'name' => 'Seguimiento y evidencias',
                'paths' => ['route:ordenes.seguimiento', 'route:api.ordenes.extras.*', 'route:api.ordenes.seguimientos.*', 'route:api.ordenes.imagenes.*'],
                'message' => 'El seguimiento de ordenes esta en mantenimiento temporal.',
            ],
            'ordenes.eliminar' => [
                'name' => 'Eliminar ordenes',
                'paths' => ['route:ordenes.destroy'],
                'message' => 'La eliminacion de ordenes esta en mantenimiento temporal.',
            ],
            'ordenes.exportar' => [
                'name' => 'Exportar ordenes',
                'paths' => ['route:ordenes.export'],
                'message' => 'La exportacion de ordenes esta en mantenimiento temporal.',
            ],
            'seguimiento' => [
                'name' => 'Modulo completo',
                'paths' => ['seguimiento*', 'api/seguimiento*'],
                'message' => 'El seguimiento de servicios esta en mantenimiento temporal.',
            ],
            'seguimiento.api' => [
                'name' => 'Datos y actualizaciones',
                'paths' => ['route:api.seguimiento-servicios'],
                'message' => 'Los datos de seguimiento estan en mantenimiento temporal.',
            ],
            'reportes' => [
                'name' => 'Modulo completo',
                'paths' => ['reportes*'],
                'message' => 'Los reportes estan en mantenimiento temporal.',
            ],
            'reportes.descargar' => [
                'name' => 'Descargar reportes',
                'paths' => ['route:reportes.descargar'],
                'message' => 'La descarga de reportes esta en mantenimiento temporal.',
            ],
            'clientes' => [
                'name' => 'Modulo completo',
                'paths' => ['clientes*'],
                'message' => 'La administracion de clientes esta en mantenimiento temporal.',
            ],
            'clientes.crear' => [
                'name' => 'Crear clientes',
                'paths' => ['route:clientes.nuevo', 'route:clientes.store'],
                'message' => 'La creacion de clientes esta en mantenimiento temporal.',
            ],
            'clientes.editar' => [
                'name' => 'Editar clientes',
                'paths' => ['route:clientes.edit', 'route:clientes.update', 'route:clientes.credito.actualizar'],
                'message' => 'La edicion de clientes esta en mantenimiento temporal.',
            ],
            'clientes.pagos' => [
                'name' => 'Pagos y credito',
                'paths' => ['route:clientes.pagos*'],
                'message' => 'Los pagos de clientes estan en mantenimiento temporal.',
            ],
            'clientes.eliminar' => [
                'name' => 'Eliminar clientes',
                'paths' => ['route:clientes.destroy'],
                'message' => 'La eliminacion de clientes esta en mantenimiento temporal.',
            ],
            'empleados' => [
                'name' => 'Modulo completo',
                'paths' => ['empleados*'],
                'message' => 'La administracion de empleados esta en mantenimiento temporal.',
            ],
            'empleados.crear' => [
                'name' => 'Crear empleados',
                'paths' => ['route:empleados.crear', 'route:empleados.store'],
                'message' => 'La creacion de empleados esta en mantenimiento temporal.',
            ],
            'empleados.editar' => [
                'name' => 'Editar empleados',
                'paths' => ['route:empleados.edit', 'route:empleados.update', 'route:empleados.verPassword'],
                'message' => 'La edicion de empleados esta en mantenimiento temporal.',
            ],
            'empleados.eliminar' => [
                'name' => 'Eliminar empleados',
                'paths' => ['route:empleados.destroy'],
                'message' => 'La eliminacion de empleados esta en mantenimiento temporal.',
            ],
            'proveedores' => [
                'name' => 'Modulo completo',
                'paths' => ['proveedores*'],
                'message' => 'La administracion de proveedores esta en mantenimiento temporal.',
            ],
            'proveedores.crear' => [
                'name' => 'Crear proveedores',
                'paths' => ['route:proveedores.nuevo', 'route:proveedores.guardar'],
                'message' => 'La creacion de proveedores esta en mantenimiento temporal.',
            ],
            'proveedores.editar' => [
                'name' => 'Editar proveedores',
                'paths' => ['route:proveedores.editar', 'route:proveedores.actualizar'],
                'message' => 'La edicion de proveedores esta en mantenimiento temporal.',
            ],
            'proveedores.eliminar' => [
                'name' => 'Eliminar proveedores',
                'paths' => ['route:proveedores.eliminar'],
                'message' => 'La eliminacion de proveedores esta en mantenimiento temporal.',
            ],
            'logistica' => [
                'name' => 'Modulo completo',
                'paths' => ['logistica*'],
                'message' => 'El modulo de logistica esta en mantenimiento temporal.',
            ],
            'logistica.direcciones' => [
                'name' => 'Busqueda de direcciones',
                'paths' => ['route:logistica.direcciones.*'],
                'message' => 'La busqueda de direcciones esta en mantenimiento temporal.',
            ],
            'logistica.movimientos' => [
                'name' => 'Jornadas y movimientos',
                'paths' => ['route:logistica.jornadas.*', 'route:logistica.movimientos.*'],
                'message' => 'Los movimientos logisticos estan en mantenimiento temporal.',
            ],
            'tecnico' => [
                'name' => 'Modulo completo',
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
            $section = MaintenanceSection::firstOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'paths' => $data['paths'],
                    'message' => $data['message'],
                    'enabled' => false,
                ]
            );

            $section->fill([
                'name' => $data['name'],
                'paths' => $data['paths'],
            ]);

            if (blank($section->message)) {
                $section->message = $data['message'];
            }

            if ($section->isDirty()) {
                $section->save();
            }
        }

        return MaintenanceSection::query()
            ->orderBy('name')
            ->get();
    }
}
