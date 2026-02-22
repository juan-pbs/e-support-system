<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('detalle_cotizacion_servicio', function (Blueprint $table) {
            $table->id('id_detalle_servicio');
            $table->unsignedBigInteger('id_cotizacion');
            $table->string('nombre', 255);
            $table->string('descripcion')->nullable();
            $table->decimal('duracion', 10, 2);
            $table->decimal('precio', 10, 2);
            $table->timestamps();

            // Foreign key
            $table->foreign('id_cotizacion')->references('id_cotizacion')->on('cotizaciones')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detalle_cotizacion_servicio');
    }
};
