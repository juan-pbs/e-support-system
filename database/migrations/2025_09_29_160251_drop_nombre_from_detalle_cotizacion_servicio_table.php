<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nota: En algunas versiones de Laravel/MySQL podría requerirse:
        // composer require doctrine/dbal
        if (Schema::hasColumn('detalle_cotizacion_servicio', 'nombre')) {
            Schema::table('detalle_cotizacion_servicio', function (Blueprint $table) {
                $table->dropColumn('nombre');
            });
        }
    }

    public function down(): void
    {
        // Restauramos la columna como nullable para evitar problemas al revertir
        Schema::table('detalle_cotizacion_servicio', function (Blueprint $table) {
            if (!Schema::hasColumn('detalle_cotizacion_servicio', 'nombre')) {
                $table->string('nombre', 255)->nullable()->after('id_cotizacion');
            }
        });
    }
};
