<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Campos nuevos para finanzas y firmas en la orden
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (!Schema::hasColumn('orden_servicio', 'moneda')) {
                $table->string('moneda', 3)->default('MXN')->after('prioridad');
            }
            if (!Schema::hasColumn('orden_servicio', 'tasa_cambio')) {
                $table->decimal('tasa_cambio', 12, 6)->default(1)->after('moneda');
            }
            if (!Schema::hasColumn('orden_servicio', 'impuestos')) {
                $table->decimal('impuestos', 12, 2)->default(0)->after('tasa_cambio');
            }
            if (!Schema::hasColumn('orden_servicio', 'archivo_pdf')) {
                $table->string('archivo_pdf')->nullable()->after('impuestos');
            }
            if (!Schema::hasColumn('orden_servicio', 'autorizado_por')) {
                $table->string('autorizado_por')->nullable()->after('archivo_pdf');
            }

            // Firma rápida (conformidad) para previsualización/descarga
            if (!Schema::hasColumn('orden_servicio', 'firma_conformidad')) {
                // base64 o data URL; longText por el tamaño del PNG
                $table->longText('firma_conformidad')->nullable()->after('autorizado_por');
            }

            // Firmas separadas (Acta): empleado / responsable (path y/o base64)
            if (!Schema::hasColumn('orden_servicio', 'firma_emp_path')) {
                $table->string('firma_emp_path')->nullable()->after('firma_conformidad');
            }
            if (!Schema::hasColumn('orden_servicio', 'firma_emp_data')) {
                $table->longText('firma_emp_data')->nullable()->after('firma_emp_path');
            }
            if (!Schema::hasColumn('orden_servicio', 'firma_resp_path')) {
                $table->string('firma_resp_path')->nullable()->after('firma_emp_data');
            }
            if (!Schema::hasColumn('orden_servicio', 'firma_resp_data')) {
                $table->longText('firma_resp_data')->nullable()->after('firma_resp_path');
            }

            // Metadatos del PDF del Acta
            if (!Schema::hasColumn('orden_servicio', 'acta_pdf_path')) {
                $table->string('acta_pdf_path')->nullable();
            }
            if (!Schema::hasColumn('orden_servicio', 'acta_pdf_hash')) {
                $table->string('acta_pdf_hash', 64)->nullable();
            }
            if (!Schema::hasColumn('orden_servicio', 'acta_firmada_at')) {
                $table->timestamp('acta_firmada_at')->nullable();
            }
            if (!Schema::hasColumn('orden_servicio', 'acta_estado')) {
                $table->string('acta_estado', 20)->default('borrador');
            }
            if (!Schema::hasColumn('orden_servicio', 'acta_data')) {
                $table->json('acta_data')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            foreach ([
                'moneda','tasa_cambio','impuestos','archivo_pdf','autorizado_por',
                'firma_conformidad',
                'firma_emp_path','firma_emp_data','firma_resp_path','firma_resp_data',
                'acta_pdf_path','acta_pdf_hash','acta_firmada_at','acta_estado','acta_data',
            ] as $col) {
                if (Schema::hasColumn('orden_servicio', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
