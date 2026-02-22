<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            // Rutas de archivos en Storage (si decides persistir la imagen en disco)
            if (!Schema::hasColumn('orden_servicio', 'firma_autorizacion')) {
                $table->string('firma_autorizacion')->nullable();
            }
            if (!Schema::hasColumn('orden_servicio', 'firma_conformidad')) {
                $table->string('firma_conformidad')->nullable();
            }

            // Guardar la imagen en base64 directamente (opcional, útil para DomPDF)
            if (!Schema::hasColumn('orden_servicio', 'firma_base64')) {
                $table->longText('firma_base64')->nullable();
            }

            // Ruta genérica por si tu código usa 'firma_path'
            if (!Schema::hasColumn('orden_servicio', 'firma_path')) {
                $table->string('firma_path')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_servicio', function (Blueprint $table) {
            if (Schema::hasColumn('orden_servicio', 'firma_path'))         $table->dropColumn('firma_path');
            if (Schema::hasColumn('orden_servicio', 'firma_base64'))       $table->dropColumn('firma_base64');
            if (Schema::hasColumn('orden_servicio', 'firma_conformidad'))  $table->dropColumn('firma_conformidad');
            if (Schema::hasColumn('orden_servicio', 'firma_autorizacion')) $table->dropColumn('firma_autorizacion');
        });
    }
};
