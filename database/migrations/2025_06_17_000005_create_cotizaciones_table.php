<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id('id_cotizacion');

            $table->string('tipo_solicitud')->nullable();
            $table->unsignedBigInteger('registro_cliente')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('importe', 10, 2)->default(0);
            $table->decimal('iva', 6, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('cantidad_escrita', 100);
            $table->string('moneda', 3)->default('MXN');
            $table->decimal('costo_operativo', 10, 2)->default(0);
            $table->date('fecha')->nullable();
            $table->date('vigencia');
            $table->timestamps();

            // Relación con cliente
            $table->foreign('registro_cliente')->references('clave_cliente')->on('cliente')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizaciones');
    }
};
