<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Columnas opcionales (solo si no existen)
            if (!Schema::hasColumn('productos','num_identificacion')) {
                $table->string('num_identificacion', 100)->nullable()->after('numero_parte');
            }
            if (!Schema::hasColumn('productos','clave_prodserv')) {
                $table->string('clave_prodserv', 10)->nullable()->after('categoria');
            }
            if (!Schema::hasColumn('productos','clave_unidad')) {
                $table->string('clave_unidad', 10)->nullable()->after('clave_prodserv');
            }
            if (!Schema::hasColumn('productos','unidad')) {
                $table->string('unidad', 50)->nullable()->after('clave_unidad');
            }
            if (!Schema::hasColumn('productos','unidad_desc')) {
                $table->string('unidad_desc', 50)->nullable()->after('unidad');
            }
            if (!Schema::hasColumn('productos','stock_seguridad')) {
                $table->unsignedInteger('stock_seguridad')->default(0)->after('unidad_desc');
            }
            if (!Schema::hasColumn('productos','activo')) {
                $table->boolean('activo')->default(true)->after('stock_seguridad');
            }
            if (!Schema::hasColumn('productos','require_serie')) {
                $table->boolean('require_serie')->default(false)->after('activo');
            }
        });

        // ===== Índices seguros (no duplican si ya existen) =====
        // Unique numero_parte
        $exists = DB::selectOne("
            SELECT COUNT(1) AS c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'productos'
              AND INDEX_NAME = 'productos_numero_parte_unique'
        ");
        if (!$exists || (int)$exists->c === 0) {
            DB::statement("ALTER TABLE `productos` ADD UNIQUE `productos_numero_parte_unique`(`numero_parte`)");
        }

        // Índices para búsqueda (si no existen)
        $addIndexIfMissing = function(string $idx, string $col) {
            $row = DB::selectOne("
                SELECT COUNT(1) AS c
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'productos'
                  AND INDEX_NAME = ?
            ", [$idx]);
            if (!$row || (int)$row->c === 0) {
                DB::statement("CREATE INDEX `$idx` ON `productos`(`$col`)");
            }
        };

        $addIndexIfMissing('productos_num_identificacion_idx', 'num_identificacion');
        $addIndexIfMissing('productos_categoria_idx', 'categoria');
        $addIndexIfMissing('productos_clave_prodserv_idx', 'clave_prodserv');
        $addIndexIfMissing('productos_activo_idx', 'activo');
    }

    public function down(): void
    {
        // Puedes omitir el down si no te interesa revertir índices
        try { DB::statement("DROP INDEX `productos_num_identificacion_idx` ON `productos`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `productos_categoria_idx` ON `productos`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `productos_clave_prodserv_idx` ON `productos`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `productos_activo_idx` ON `productos`"); } catch (\Throwable $e) {}
        try { DB::statement("DROP INDEX `productos_numero_parte_unique` ON `productos`"); } catch (\Throwable $e) {}
    }
};
