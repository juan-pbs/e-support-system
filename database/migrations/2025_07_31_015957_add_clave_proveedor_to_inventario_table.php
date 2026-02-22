<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->unsignedBigInteger('clave_proveedor')->after('codigo_producto')->nullable();

            $table->foreign('clave_proveedor')
                  ->references('clave_proveedor')
                  ->on('proveedores')
                  ->onDelete('set null'); // o 'restrict' si prefieres
        });
    }

    public function down(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropForeign(['clave_proveedor']);
            $table->dropColumn('clave_proveedor');
        });
    }
};
