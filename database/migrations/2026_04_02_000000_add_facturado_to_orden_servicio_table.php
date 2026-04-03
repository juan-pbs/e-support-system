<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orden_servicio') || Schema::hasColumn('orden_servicio', 'facturado')) {
            return;
        }

        Schema::table('orden_servicio', function (Blueprint $table) {
            $table->boolean('facturado')
                ->default(false)
                ->after('tipo_pago')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orden_servicio') || ! Schema::hasColumn('orden_servicio', 'facturado')) {
            return;
        }

        Schema::table('orden_servicio', function (Blueprint $table) {
            $table->dropColumn('facturado');
        });
    }
};
