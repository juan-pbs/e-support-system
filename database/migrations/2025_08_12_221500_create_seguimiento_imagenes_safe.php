<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Si ya existe, no hacemos nada (evita 42S01)
        if (Schema::hasTable('seguimiento_imagenes')) {
            return;
        }

        // 1) Asegura motor InnoDB en la tabla padre
        if (Schema::hasTable('seguimiento_servicio')) {
            DB::statement('ALTER TABLE seguimiento_servicio ENGINE=InnoDB;');
        } else {
            // Si no existe la tabla padre, salimos para no crear una FK inválida
            throw new RuntimeException("La tabla 'seguimiento_servicio' no existe. Crea esa tabla antes.");
        }

        // 2) Detecta tipo/unsigned del id_seguimiento en la tabla padre
        $col = DB::selectOne("
            SELECT DATA_TYPE, COLUMN_TYPE
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'seguimiento_servicio'
              AND COLUMN_NAME = 'id_seguimiento'
            LIMIT 1
        ");

        if (!$col) {
            throw new RuntimeException("No se encontró la columna 'id_seguimiento' en 'seguimiento_servicio'.");
        }

        $columnType = strtolower($col->COLUMN_TYPE ?? 'int unsigned'); // p.ej. 'int unsigned', 'bigint unsigned', 'int'
        $isBig      = str_contains($columnType, 'bigint');
        $isUnsigned = str_contains($columnType, 'unsigned');

        // 3) Crea la tabla hija con el tipo correcto
        Schema::create('seguimiento_imagenes', function (Blueprint $table) use ($isBig, $isUnsigned) {
            $table->engine = 'InnoDB';

            $table->bigIncrements('id_imagen');

            // FK con el mismo tipo que la PK/columna referenciada
            if ($isBig && $isUnsigned) {
                $table->unsignedBigInteger('id_seguimiento');
            } elseif ($isBig && !$isUnsigned) {
                $table->bigInteger('id_seguimiento');
            } elseif (!$isBig && $isUnsigned) {
                $table->unsignedInteger('id_seguimiento');
            } else {
                $table->integer('id_seguimiento');
            }

            $table->string('ruta');                 // p.ej. 'seguimientos/abc123.webp' (disk 'public')
            $table->string('titulo')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            // Índices y FK
            $table->index(['id_seguimiento', 'orden']);

            $table->foreign('id_seguimiento')
                  ->references('id_seguimiento')
                  ->on('seguimiento_servicio')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguimiento_imagenes');
    }
};
