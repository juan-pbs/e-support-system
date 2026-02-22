<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            // ✅ Anticipo en MXN base (para consistencia con total_adicional_mxn)
            if (!Schema::hasColumn('orden_servicio', 'anticipo_mxn')) {
                $table->decimal('anticipo_mxn', 12, 2)
                    ->default(0)
                    ->after('total_adicional_mxn');
            }

            // ✅ Porcentaje del total final pagado como anticipo (0 - 100)
            if (!Schema::hasColumn('orden_servicio', 'anticipo_porcentaje')) {
                $table->decimal('anticipo_porcentaje', 5, 2)
                    ->nullable()
                    ->after('anticipo_mxn');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('orden_servicio', 'anticipo_porcentaje')) {
                $table->dropColumn('anticipo_porcentaje');
            }
            if (Schema::hasColumn('orden_servicio', 'anticipo_mxn')) {
                $table->dropColumn('anticipo_mxn');
            }
        });
    }
};
