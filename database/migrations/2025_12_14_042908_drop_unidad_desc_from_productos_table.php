<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'unidad_desc')) {
                $table->dropColumn('unidad_desc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Revertir: vuelve a crear la columna (ajusta si antes era nullable o con default distinto)
            if (!Schema::hasColumn('productos', 'unidad_desc')) {
                $table->string('unidad_desc', 50)->nullable()->after('unidad');
            }
        });
    }
};
