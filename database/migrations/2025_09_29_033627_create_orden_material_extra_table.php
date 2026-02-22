<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('orden_material_extra')) {
            Schema::create('orden_material_extra', function (Blueprint $table) {
                $table->bigIncrements('id_extra');
                $table->unsignedBigInteger('id_orden_servicio');
                $table->text('descripcion')->nullable();
                $table->integer('cantidad')->default(1);
                $table->decimal('precio_unitario', 12, 2)->default(0);
                $table->decimal('total', 12, 2)->default(0);
                $table->string('moneda', 3)->default('MXN');
                $table->timestamps();

                $table->foreign('id_orden_servicio')
                      ->references('id_orden_servicio')->on('orden_servicio')
                      ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orden_material_extra')) {
            Schema::dropIfExists('orden_material_extra');
        }
    }
};
