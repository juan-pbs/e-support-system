<?php
// database/migrations/2025_08_26_000003_add_rfc_alias_to_proveedores.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('proveedores', function (Blueprint $t) {
      if (!Schema::hasColumn('proveedores','rfc'))   $t->string('rfc', 20)->nullable()->after('nombre');
      if (!Schema::hasColumn('proveedores','alias')) $t->string('alias', 60)->nullable()->after('rfc');
      $t->index('rfc');
    });
  }
  public function down(): void {
    Schema::table('proveedores', function (Blueprint $t) {
      if (Schema::hasColumn('proveedores','alias')) $t->dropColumn('alias');
      if (Schema::hasColumn('proveedores','rfc'))   $t->dropColumn('rfc');
    });
  }
};

