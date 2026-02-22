<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Hacer campos nullable
        Schema::table('cliente', function (Blueprint $table) {
            $table->string('correo_electronico', 255)->nullable()->change();
            $table->string('direccion_fiscal', 255)->nullable()->change();

            // Si también quieres limpiar/permitir NULL en datos_fiscales, descomenta:
            // $table->string('datos_fiscales', 255)->nullable()->change();
        });

        // 2) Limpiar valores existentes (dejarlos en NULL)
        DB::table('cliente')->update([
            'correo_electronico' => null,
            'direccion_fiscal'   => null,

            // Si también quieres limpiar datos_fiscales, descomenta:
            // 'datos_fiscales'  => null,
        ]);
    }

    public function down(): void
    {
        // Revertir a NOT NULL (para poder hacerlo, primero evitamos NULL)
        DB::table('cliente')->whereNull('correo_electronico')->update(['correo_electronico' => '']);
        DB::table('cliente')->whereNull('direccion_fiscal')->update(['direccion_fiscal' => '']);

        Schema::table('cliente', function (Blueprint $table) {
            $table->string('correo_electronico', 255)->nullable(false)->default('')->change();
            $table->string('direccion_fiscal', 255)->nullable(false)->default('')->change();

            // Si aplicaste datos_fiscales arriba, revierte también aquí:
            // DB::table('cliente')->whereNull('datos_fiscales')->update(['datos_fiscales' => '']);
            // $table->string('datos_fiscales', 255)->nullable(false)->default('')->change();
        });
    }
};
