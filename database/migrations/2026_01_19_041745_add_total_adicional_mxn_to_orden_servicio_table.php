<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (!Schema::hasColumn('orden_servicio', 'total_adicional_mxn')) {
                // Captura base SIEMPRE en MXN (solo suma extras con precio asignado)
                $table->decimal('total_adicional_mxn', 12, 2)->default(0)->after('impuestos');
            }
        });

        // Backfill (rellenar) para órdenes existentes
        // Total = SUM(cantidad * precio_unitario) solo donde precio_unitario NO sea NULL
        try {
            DB::statement("
                UPDATE orden_servicio os
                LEFT JOIN (
                    SELECT id_orden_servicio,
                           COALESCE(SUM(cantidad * precio_unitario), 0) AS total
                    FROM orden_material_extra
                    WHERE precio_unitario IS NOT NULL
                    GROUP BY id_orden_servicio
                ) t ON t.id_orden_servicio = os.id_orden_servicio
                SET os.total_adicional_mxn = COALESCE(t.total, 0)
            ");
        } catch (\Throwable $e) {
            // Si tu motor no permite esto, no rompemos migración
        }
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('orden_servicio', 'total_adicional_mxn')) {
                $table->dropColumn('total_adicional_mxn');
            }
        });
    }
};
