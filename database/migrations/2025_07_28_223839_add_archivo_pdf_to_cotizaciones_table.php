<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
{
    Schema::table('cotizaciones', function (Blueprint $table) {
        $table->string('archivo_pdf')->nullable()->after('total');
    });
}

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            //
        });
    }
};
