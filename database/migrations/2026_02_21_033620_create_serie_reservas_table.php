<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serie_reservas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('codigo_producto');
            $table->string('numero_serie');

            $table->string('token', 80);
            $table->unsignedBigInteger('user_id')->nullable();

            $table->enum('estado', ['reservado', 'asignado'])->default('reservado');

            $table->dateTime('reserved_at')->nullable();
            $table->dateTime('expires_at')->nullable();

            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->dateTime('assigned_at')->nullable();

            $table->timestamps();

            $table->unique(['codigo_producto', 'numero_serie']);
            $table->index(['codigo_producto', 'estado']);
            $table->index(['token', 'estado']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serie_reservas');
    }
};
