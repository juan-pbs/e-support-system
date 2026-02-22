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
                $table->decimal('impuesto', 10, 2)->default(0)->after('precio_unitario');
            }
            if (!Schema::hasColumn('detalle_orden_producto', 'moneda')) {
                $table->string('moneda', 3)->default('MXN')->after('impuesto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('detalle_orden_producto', function (Blueprint $table) {
            if (Schema::hasColumn('detalle_orden_producto', 'descripcion')) {
                $table->dropColumn('descripcion');
            }
            if (Schema::hasColumn('detalle_orden_producto', 'impuesto')) {
                $table->dropColumn('impuesto');
            }
            if (Schema::hasColumn('detalle_orden_producto', 'moneda')) {
                $table->dropColumn('moneda');
            }
        });
    }
};
