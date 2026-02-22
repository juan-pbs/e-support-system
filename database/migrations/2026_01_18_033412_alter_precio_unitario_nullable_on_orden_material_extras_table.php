<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orden_material_extra', function (Blueprint $table) {
            // precio_unitario -> NULL = "pendiente"
            $table->decimal('precio_unitario', 12, 2)->nullable()->change();

            // si tienes subtotal, conviene nullable también
            if (Schema::hasColumn('orden_material_extra', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orden_material_extra', function (Blueprint $table) {
            // vuelve a NO nullable con default 0
            $table->decimal('precio_unitario', 12, 2)->default(0)->change();

            if (Schema::hasColumn('orden_material_extra', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->change();
            }
        });
    }
};
