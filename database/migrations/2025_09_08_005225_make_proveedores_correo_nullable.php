<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Si usas ->change(), necesitas doctrine/dbal instalado.
        // composer require doctrine/dbal
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('correo', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->string('correo', 255)->nullable(false)->change();
        });
    }
};
