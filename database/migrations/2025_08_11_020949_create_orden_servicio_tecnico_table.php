<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_servicio_tecnico', function (Blueprint $table) {
            $table->id();

            // Claves foráneas
            $table->unsignedBigInteger('id_orden_servicio');
            $table->unsignedBigInteger('user_id'); // técnico

            // Índices
            $table->unique(['id_orden_servicio', 'user_id']);
            $table->index('user_id');

            $table->timestamps();

            // FKs
            $table->foreign('id_orden_servicio')
                  ->references('id_orden_servicio')->on('orden_servicio')
                  ->onDelete('cascade');

            $table->foreign('user_id')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });

        // Backfill opcional: copiar el técnico actual (id_tecnico) a la pivote
        if (Schema::hasColumn('orden_servicio', 'id_tecnico')) {
            $nowExpr = DB::getDriverName() === 'sqlite'
                ? "datetime('now')"
                : 'CURRENT_TIMESTAMP';

            DB::statement("
                INSERT INTO orden_servicio_tecnico (id_orden_servicio, user_id, created_at, updated_at)
                SELECT id_orden_servicio, id_tecnico, {$nowExpr}, {$nowExpr}
                FROM orden_servicio
                WHERE id_tecnico IS NOT NULL
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_servicio_tecnico');
    }
};

