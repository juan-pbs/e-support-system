<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            // Solo agregamos campos que aún NO existen
            if (!Schema::hasColumn('orden_servicio', 'tipo_pago')) {
                $table->enum('tipo_pago', ['efectivo', 'transferencia', 'tarjeta', 'crédito_cliente'])
                    ->default('efectivo')
                    ->after('condiciones_generales');
            }

            if (!Schema::hasColumn('orden_servicio', 'archivo_pdf')) {
                $table->string('archivo_pdf')->nullable()->after('tipo_pago');
            }

            // Relaciones foráneas (si no están ya)
            if (!Schema::hasColumn('orden_servicio', 'id_cliente')) {
                $table->unsignedBigInteger('id_cliente')->after('id_cotizacion');
                $table->foreign('id_cliente')->references('clave_cliente')->on('cliente')->onDelete('cascade');
            }

            if (!Schema::hasColumn('orden_servicio', 'id_tecnico')) {
                $table->unsignedBigInteger('id_tecnico')->nullable()->after('id_cliente');
                $table->foreign('id_tecnico')->references('id')->on('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            // Eliminar relaciones si existen
            $table->dropForeign(['id_cliente']);
            $table->dropForeign(['id_tecnico']);

            $table->dropColumn([
                'tipo_pago',
                'archivo_pdf',
                'id_cliente',
                'id_tecnico',
            ]);
        });
    }
};
