<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_producto')->nullable()->change();
            $table->integer('cantidad')->nullable()->change();
            $table->decimal('precio_unitario', 10, 2)->nullable()->change();
            $table->decimal('total', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
            $table->unsignedBigInteger('codigo_producto')->nullable(false)->change();
            $table->integer('cantidad')->nullable(false)->change();
            $table->decimal('precio_unitario', 10, 2)->nullable(false)->change();
            $table->decimal('total', 10, 2)->nullable(false)->change();
        });
    }
};
