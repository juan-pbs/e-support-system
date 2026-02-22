<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('detalle_orden_producto', function (Blueprint $table) {
            if (Schema::hasColumn('detalle_orden_producto', 'unidad')) {
                $table->dropColumn('unidad');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('detalle_orden_producto', function (Blueprint $table) {
            $table->string('unidad', 255)->nullable();
        });
    }
};
