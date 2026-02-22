<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) Eliminar columna duplicada 'requiere_serie' si existe (sin requerir doctrine/dbal)
        $col = DB::selectOne("
            SELECT COUNT(*) c
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'productos'
              AND COLUMN_NAME = 'requiere_serie'
        ");
        if ($col && (int)$col->c > 0) {
            try {
                DB::statement("ALTER TABLE `productos` DROP COLUMN `requiere_serie`");
            } catch (\Throwable $e) {
                // no-op si ya no existe o no se puede eliminar
            }
        }

        // 2) Asegurar índices para búsquedas (si no existen)
        $ensureIndex = function (string $indexName, string $column) {
            $idx = DB::selectOne("
                SELECT COUNT(*) c
                FROM INFORMATION_SCHEMA.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'productos'
                  AND INDEX_NAME = ?
            ", [$indexName]);

            if (!$idx || (int)$idx->c === 0) {
                DB::statement("CREATE INDEX `$indexName` ON `productos`(`$column`)");
            }
        };

        $ensureIndex('productos_num_identificacion_idx', 'num_identificacion');
        $ensureIndex('productos_categoria_idx', 'categoria');
        $ensureIndex('productos_clave_prodserv_idx', 'clave_prodserv');
        $ensureIndex('productos_activo_idx', 'activo');

        // 3) Asegurar UNIQUE de numero_parte (no fallará si ya existe)
        $uniq = DB::selectOne("
            SELECT COUNT(*) c
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'productos'
              AND INDEX_NAME = 'productos_numero_parte_unique'
        ");
        if (!$uniq || (int)$uniq->c === 0) {
            try {
                DB::statement("ALTER TABLE `productos` ADD UNIQUE `productos_numero_parte_unique`(`numero_parte`)");
            } catch (\Throwable $e) {
                // Si ya hay otro índice unique, lo dejamos
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
