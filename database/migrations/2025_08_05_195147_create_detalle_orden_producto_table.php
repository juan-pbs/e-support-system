<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_orden_producto', function (Blueprint $table) {
            $table->id('id_orden_producto');
            $table->unsignedBigInteger('id_orden_servicio');
            $table->unsignedBigInteger('codigo_producto')->nullable();

            $table->string('nombre_producto');
            $table->string('unidad')->nullable();
            $table->integer('cantidad');
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total', 10, 2);

            $table->timestamps();

            $table->foreign('id_orden_servicio')->references('id_orden_servicio')->on('orden_servicio')->onDelete('cascade');
            $table->foreign('codigo_producto')->references('codigo_producto')->on('productos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_orden_producto');
    }
};
