<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            // Campos de firma (snapshots por cotización)
            if (!Schema::hasColumn('cotizaciones', 'firmante_nombre')) {
                $table->string('firmante_nombre')->nullable()->after('cantidad_escrita');
            }
            if (!Schema::hasColumn('cotizaciones', 'firmante_puesto')) {
                $table->string('firmante_puesto')->nullable()->after('firmante_nombre');
            }
            if (!Schema::hasColumn('cotizaciones', 'firmante_empresa')) {
                $table->string('firmante_empresa')->nullable()->after('firmante_puesto');
            }
            if (!Schema::hasColumn('cotizaciones', 'signature_image')) {
                // Base64 puede ser grande
                $table->longText('signature_image')->nullable()->after('firmante_empresa');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'signature_image')) {
                $table->dropColumn('signature_image');
            }
            if (Schema::hasColumn('cotizaciones', 'firmante_empresa')) {
                $table->dropColumn('firmante_empresa');
            }
            if (Schema::hasColumn('cotizaciones', 'firmante_puesto')) {
                $table->dropColumn('firmante_puesto');
            }
            if (Schema::hasColumn('cotizaciones', 'firmante_nombre')) {
                $table->dropColumn('firmante_nombre');
            }
        });
    }
};
