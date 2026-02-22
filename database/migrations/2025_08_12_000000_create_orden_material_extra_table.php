<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orden_material_extra', function (Blueprint $table) {
            $table->id('id_material_extra');
            $table->unsignedBigInteger('id_orden_servicio');
            $table->string('descripcion', 255);
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->decimal('precio_unitario', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('id_orden_servicio')
                ->references('id_orden_servicio')->on('orden_servicio')
                ->onDelete('cascade');
            $table->index('id_orden_servicio');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_material_extra');
    }
};
