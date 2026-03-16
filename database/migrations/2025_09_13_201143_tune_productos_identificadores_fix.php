<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();
        $indexExists = function (string $indexName) use ($driver): bool {
            return match ($driver) {
                'sqlite' => collect(DB::select("PRAGMA index_list('productos')"))
                    ->contains(fn($row) => (($row->name ?? null) === $indexName)),
                'mysql' => (int) (DB::selectOne("
                    SELECT COUNT(1) AS c
                    FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'productos'
                      AND INDEX_NAME = ?
                ", [$indexName])->c ?? 0) > 0,
                default => false,
            };
        };

        $createIndex = function (string $name, string $sql) use ($indexExists): void {
            if ($indexExists($name)) {
                return;
            }

            try {
                DB::statement($sql);
            } catch (\Throwable $e) {
                // noop: evita romper migraciones si el motor ya creó el índice o no lo soporta igual
            }
        };

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
        $createIndex('productos_numero_parte_unique', "CREATE UNIQUE INDEX productos_numero_parte_unique ON productos(numero_parte)");

        // Índices para búsqueda (si no existen)
        $addIndexIfMissing = function(string $idx, string $col) use ($createIndex) {
            $createIndex($idx, "CREATE INDEX {$idx} ON productos({$col})");
        };

        $addIndexIfMissing('productos_num_identificacion_idx', 'num_identificacion');
        $addIndexIfMissing('productos_categoria_idx', 'categoria');
        $addIndexIfMissing('productos_clave_prodserv_idx', 'clave_prodserv');
        $addIndexIfMissing('productos_activo_idx', 'activo');
    }

    public function down(): void
    {
        // Puedes omitir el down si no te interesa revertir índices
        foreach ([
            'productos_num_identificacion_idx',
            'productos_categoria_idx',
            'productos_clave_prodserv_idx',
            'productos_activo_idx',
            'productos_numero_parte_unique',
        ] as $indexName) {
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
    }
};
