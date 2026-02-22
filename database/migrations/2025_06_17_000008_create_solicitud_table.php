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
        Schema::create('solicitud', function (Blueprint $table) {
            $table->id('id_solicitud'); // PK
            $table->date('fecha_solicitud'); // Fecha de la solicitud
            $table->string('quien_solicita', 255); // Nombre de quien solicita
            $table->unsignedBigInteger('autorizado_por'); //FK  Usuario que autoriza la solicitud
            $table->string('estado', 50)->default('pendiente'); // Estado de la solicitud (pendiente, aprobado, rechazado)
            $table->unsignedBigInteger('cotizacion'); //Cotizacion asociada
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('autorizado_por')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('cotizacion')->references('id_cotizacion')->on('cotizaciones')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud');
    }
};
