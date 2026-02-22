<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
{
    Schema::table('cotizaciones', function (Blueprint $table) {
        $table->decimal('tasa_cambio', 10, 6)
              ->nullable()
              ->after('moneda');
    });
}

public function down()
{
    Schema::table('cotizaciones', function (Blueprint $table) {
        $table->dropColumn('tasa_cambio');
    });
}

};
