<?php

use Illuminate\Database\Migrations\Migration;
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
                    SELECT COUNT(*) c
                    FROM INFORMATION_SCHEMA.STATISTICS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND TABLE_NAME = 'productos'
                      AND INDEX_NAME = ?
                ", [$indexName])->c ?? 0) > 0,
                default => false,
            };
        };

        // 1) Eliminar columna duplicada 'requiere_serie' si existe (sin requerir doctrine/dbal)
        if (Schema::hasColumn('productos', 'requiere_serie')) {
            try {
                DB::statement("ALTER TABLE `productos` DROP COLUMN `requiere_serie`");
            } catch (\Throwable $e) {
                // no-op si ya no existe o no se puede eliminar
            }
        }

        // 2) Asegurar índices para búsquedas (si no existen)
        $ensureIndex = function (string $indexName, string $column) use ($indexExists) {
            if ($indexExists($indexName)) {
                return;
            }

            try {
                DB::statement("CREATE INDEX {$indexName} ON productos({$column})");
            } catch (\Throwable $e) {
                // no-op si ya existe o el motor no requiere este ajuste
            }
        };

        $ensureIndex('productos_num_identificacion_idx', 'num_identificacion');
        $ensureIndex('productos_categoria_idx', 'categoria');
        $ensureIndex('productos_clave_prodserv_idx', 'clave_prodserv');
        $ensureIndex('productos_activo_idx', 'activo');

        // 3) Asegurar UNIQUE de numero_parte (no fallará si ya existe)
        if (!$indexExists('productos_numero_parte_unique')) {
            try {
                DB::statement("CREATE UNIQUE INDEX productos_numero_parte_unique ON productos(numero_parte)");
            } catch (\Throwable $e) {
                // Si ya hay otro índice unique o hay duplicados, lo dejamos
            }
        }
    }

    public function down(): void
    {
        // Revertir índices (opcional)
        foreach ([
            'productos_num_identificacion_idx',
            'productos_categoria_idx',
            'productos_clave_prodserv_idx',
            'productos_activo_idx',
        ] as $idx) {
            try { DB::statement("DROP INDEX `$idx` ON `productos`"); } catch (\Throwable $e) {}
        }
        // No recreamos 'requiere_serie' para no volver al estado inconsistente
    }
};
