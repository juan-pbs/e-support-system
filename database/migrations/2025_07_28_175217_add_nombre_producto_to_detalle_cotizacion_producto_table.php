<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
        $table->string('nombre_producto')->nullable()->after('codigo_producto');
    });
}

public function down()
{
    Schema::table('detalle_cotizacion_producto', function (Blueprint $table) {
        $table->dropColumn('nombre_producto');
    });
}

};
