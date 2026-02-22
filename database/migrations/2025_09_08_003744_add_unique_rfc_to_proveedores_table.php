<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            // Asegura longitud adecuada y requerido (opcional: si aún no tienes todos los RFC, quita ->nullable(false))
            $table->string('rfc', 20)->nullable(false)->change();

            // Índice único por RFC
            $table->unique('rfc', 'proveedores_rfc_unique');

            // Si en algún entorno llegaste a poner unique al correo, puedes retirar el índice:
            $table->dropUnique('proveedores_correo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropUnique('proveedores_rfc_unique');
            // Si quitaste el unique de correo en up(), podrías restaurarlo aquí:
            // $table->unique('correo', 'proveedores_correo_unique');
            // $table->string('rfc', 20)->nullable()->change(); // si quieres volver a nullable
        });
    }
};
