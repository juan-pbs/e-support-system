<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->decimal('iva', 10, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        $hasOutOfRangeValues = DB::table('cotizaciones')
            ->where('iva', '>', 9999.99)
            ->orWhere('iva', '<', -9999.99)
            ->exists();

        if ($hasOutOfRangeValues) {
            throw new RuntimeException('No se puede revertir cotizaciones.iva a decimal(6,2) porque existen valores fuera del rango permitido.');
        }

        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->decimal('iva', 6, 2)->default(0)->change();
        });
    }
};
