<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Por si ya no existen en algún ambiente
            if (Schema::hasColumn('productos', 'num_identificacion')) {
                $table->dropColumn('num_identificacion');
            }
            if (Schema::hasColumn('productos', 'clave_unidad')) {
                $table->dropColumn('clave_unidad');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Ajusta los tipos si en tu BD eran diferentes
            if (!Schema::hasColumn('productos', 'num_identificacion')) {
                $table->string('num_identificacion')->nullable()->after('numero_parte');
            }
            if (!Schema::hasColumn('productos', 'clave_unidad')) {
                $table->string('clave_unidad')->nullable()->after('clave_prodserv');
            }
        });
    }
};
