<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
    Schema::create('numeros_serie', function (Blueprint $table) {
        $table->id();
        $table->string('numero_serie');
        $table->foreignId('inventario_id')->constrained('inventario')->onDelete('cascade');
        $table->timestamps();
    });
}


public function down(): void
{
    Schema::dropIfExists('numeros_serie');
}


};
