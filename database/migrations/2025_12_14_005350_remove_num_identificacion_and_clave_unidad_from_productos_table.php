<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'clave_unidad')) {
                $table->dropColumn('clave_unidad');
            }
        });

        if (Schema::hasColumn('productos', 'num_identificacion')) {
            foreach (['productos_num_identificacion_idx'] as $indexName) {
                try {
                    DB::statement("DROP INDEX {$indexName}");
                } catch (\Throwable $e) {
                    try {
                        DB::statement("DROP INDEX {$indexName} ON productos");
                    } catch (\Throwable $e) {
                        // noop
                    }
                }
            }

            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('num_identificacion');
            });
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos', 'num_identificacion')) {
                $table->string('num_identificacion')->nullable()->after('numero_parte');
            }

            if (!Schema::hasColumn('productos', 'clave_unidad')) {
                $table->string('clave_unidad')->nullable()->after('clave_prodserv');
            }
        });
    }
};
