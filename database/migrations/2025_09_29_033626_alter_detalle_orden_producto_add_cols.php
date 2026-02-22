<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalle_orden_producto', function (Blueprint $table) {
            if (!Schema::hasColumn('detalle_orden_producto', 'descripcion')) {
                $table->text('descripcion')->nullable()->after('nombre_producto');
            }
            if (!Schema::hasColumn('detalle_orden_producto', 'impuesto')) {
                $table->decimal('impuesto', 12, 2)->default(0)->after('total');
            }
            if (!Schema::hasColumn('detalle_orden_producto', 'moneda')) {
                $table->string('moneda', 3)->default('MXN')->after('impuesto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalle_orden_producto', function (Blueprint $table) {
            foreach (['descripcion','impuesto','moneda'] as $col) {
                if (Schema::hasColumn('detalle_orden_producto', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
