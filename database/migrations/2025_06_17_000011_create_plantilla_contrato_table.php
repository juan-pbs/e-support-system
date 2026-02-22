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
        Schema::create('plantilla_contrato', function (Blueprint $table) {
            $table->id('id_contrato'); // PK
            $table->string('nombre', 255); // Nombre del cliente
            $table->string('nombre_empresa', 255); // Nombre de la plantilla del contrato
            $table->string('direccion_fiscal', 100)->nullable(); // Dirección fiscal de la empresa
            $table->string('rfc', 13)->nullable(); // RFC de la empresa
            $table->unsignedBigInteger('id_orden_servicio'); // FK to cliente
            $table->unsignedBigInteger('id_solicitud'); // FK to cliente
            $table->date('fecha_inicio'); // Fecha de inicio del contrato
            $table->text('observaciones')->nullable(); // Descripción del contrato
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('id_orden_servicio')
                ->references('id_orden_servicio')
                ->on('orden_servicio')
                ->onDelete('cascade');
            $table->foreign('id_solicitud')
                ->references('id_solicitud')
                ->on('solicitud')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plantilla_contrato');
    }
};
