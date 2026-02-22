<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
            // Agregar descripcion_item si no existe
            if (!Schema::hasColumn('detalle_cotizacion_producto', 'descripcion_item')) {
                $table->text('descripcion_item')->nullable()->after('nombre_producto');
            }

            // Agregar unidad si no existe
            if (!Schema::hasColumn('detalle_cotizacion_producto', 'unidad')) {
                $table->string('unidad', 50)->default('unidad')->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
            if (Schema::hasColumn('detalle_cotizacion_producto', 'descripcion_item')) {
                $table->dropColumn('descripcion_item');
            }
            if (Schema::hasColumn('detalle_cotizacion_producto', 'unidad')) {
                $table->dropColumn('unidad');
            }
        });
    }
};
