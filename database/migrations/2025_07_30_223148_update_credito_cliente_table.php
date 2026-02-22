<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credito_cliente', function (Blueprint $table) {
            // Renombrar columnas
            $table->renameColumn('monto', 'monto_maximo');
            $table->renameColumn('dias', 'dias_credito');

            // Agregar nuevas columnas
            $table->decimal('monto_usado', 10, 2)->default(0)->after('monto_maximo');
            $table->date('fecha_asignacion')->nullable()->after('dias_credito');
            $table->enum('estatus', ['activo', 'bloqueado', 'vencido'])->default('activo')->after('fecha_asignacion');
        });
    }

    public function down(): void
    {
        Schema::table('credito_cliente', function (Blueprint $table) {
            // Revertir cambios
            $table->renameColumn('monto_maximo', 'monto');
            $table->renameColumn('dias_credito', 'dias');
            $table->dropColumn('monto_usado');
            $table->dropColumn('fecha_asignacion');
            $table->dropColumn('estatus');
        });
    }
};
