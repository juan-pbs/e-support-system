<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'condiciones_pago')) {
                $table->string('condiciones_pago', 255)
                    ->nullable()
                    ->after('cantidad_escrita');
            }

            if (!Schema::hasColumn('cotizaciones', 'tiempo_entrega')) {
                $table->string('tiempo_entrega', 255)
                    ->nullable()
                    ->after('condiciones_pago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'tiempo_entrega')) {
                $table->dropColumn('tiempo_entrega');
            }

            if (Schema::hasColumn('cotizaciones', 'condiciones_pago')) {
                $table->dropColumn('condiciones_pago');
            }
        });
    }
};
