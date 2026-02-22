<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detalle_orden_producto_series', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('id_orden_producto');
            $table->string('numero_serie', 191);
            $table->timestamps();

            // FK con nombre corto
            $table->foreign('id_orden_producto', 'dop_series_fk')
                ->references('id_orden_producto')
                ->on('detalle_orden_producto')
                ->cascadeOnDelete();

            // Índices con nombres cortos
            $table->unique(['id_orden_producto', 'numero_serie'], 'dop_series_uq');
            $table->index('numero_serie', 'dop_ns_idx');
        });
    }

    public function down(): void
    {
        // Al dropear, primero quitar los índices nombrados explícitamente
        Schema::table('detalle_orden_producto_series', function (Blueprint $table) {
            // Si ya fue borrado antes, estas llamadas simplemente no harán nada
            try { $table->dropForeign('dop_series_fk'); } catch (\Throwable $e) {}
            try { $table->dropUnique('dop_series_uq'); } catch (\Throwable $e) {}
            try { $table->dropIndex('dop_ns_idx'); } catch (\Throwable $e) {}
        });

        Schema::dropIfExists('detalle_orden_producto_series');
    }
};
