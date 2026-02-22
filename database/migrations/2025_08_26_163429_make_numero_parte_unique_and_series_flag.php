<?php

// database/migrations/2025_08_26_000001_make_numero_parte_unique_and_series_flag.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('productos', function (Blueprint $t) {
      if (!Schema::hasColumn('productos','requiere_serie')) {
        $t->boolean('requiere_serie')->default(false)->after('activo');
      }
      $t->string('numero_parte', 100)->nullable(false)->change();
      $t->unique('numero_parte');
    });
  }
  public function down(): void {
    Schema::table('productos', function (Blueprint $t) {
      $t->dropUnique(['numero_parte']);
      if (Schema::hasColumn('productos','requiere_serie')) $t->dropColumn('requiere_serie');
    });
  }
};

