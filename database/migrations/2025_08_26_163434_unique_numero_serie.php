<?php
// database/migrations/2025_08_26_000004_unique_numero_serie.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::hasTable('numeros_serie')) {
      Schema::table('numeros_serie', function (Blueprint $t) {
        $t->unique('numero_serie');
      });
    }
  }
  public function down(): void {
    if (Schema::hasTable('numeros_serie')) {
      Schema::table('numeros_serie', function (Blueprint $t) {
        $t->dropUnique(['numero_serie']);
      });
    }
  }
};
