<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // En algunos proyectos podría requerirse DBAL para dropColumn:
        // composer require doctrine/dbal
        if (Schema::hasColumn('detalle_cotizacion_servicio', 'duracion')) {
            Schema::table('detalle_cotizacion_servicio', function (Blueprint $table) {
                $table->dropColumn('duracion');
            });
        }
    }

    public function down(): void
    {
        Schema::table('detalle_cotizacion_servicio', function (Blueprint $table) {
            if (!Schema::hasColumn('detalle_cotizacion_servicio', 'duracion')) {
                // La restauramos como nullable para evitar errores al revertir
                $table->decimal('duracion', 10, 2)->nullable()->after('descripcion');
            }
        });
    }
};
