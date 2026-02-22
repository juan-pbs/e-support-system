<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_cotizacion_producto', function (Blueprint $table) {
            $table->id('id_cotizacion_producto');
            $table->unsignedBigInteger('id_cotizacion');
            $table->unsignedBigInteger('codigo_producto');
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total', 10, 2);
            $table->timestamps();

            // Foreign keys
            $table->foreign('id_cotizacion')->references('id_cotizacion')->on('cotizaciones')->onDelete('cascade');
            $table->foreign('codigo_producto')->references('codigo_producto')->on('productos')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizacion_producto');
    }
};
