<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seguimiento_servicio', function (Blueprint $table) {
            $table->text('observaciones')->change();
            $table->text('comentarios')->change();
        });
    }

    public function down(): void
    {
        Schema::table('seguimiento_servicio', function (Blueprint $table) {
            $table->string('observaciones')->change();
            $table->string('comentarios')->change();
        });
    }
};
