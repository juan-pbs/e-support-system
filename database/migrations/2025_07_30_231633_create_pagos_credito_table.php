<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration {
    public function up(): void
    {
        Schema::create('pagos_credito', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('clave_cliente');
            $table->decimal('monto', 10, 2);
            $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->foreign('clave_cliente')->references('clave_cliente')->on('cliente')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_credito');
    }
};
