<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Queremos que seguimiento_imagenes:
     *  - tenga id_orden_servicio (FK directa a orden_servicio)
     *  - deje de depender de id_seguimiento (seguimiento_servicio)
     */
    public function up(): void
    {
        // 1) Agregamos la columna id_orden_servicio (por ahora nullable para no romper nada)
        Schema::table('seguimiento_imagenes', function (Blueprint $table) {
            $table->unsignedBigInteger('id_orden_servicio')
                  ->nullable()
                  ->after('id_imagen');
        });

        // 2) Migramos datos existentes (si los hay) desde seguimiento_servicio → orden_servicio
        //    Si tu tabla está vacía, este UPDATE no hará nada y no pasa nada.
        DB::statement('
            UPDATE seguimiento_imagenes si
            INNER JOIN seguimiento_servicio ss
                ON si.id_seguimiento = ss.id_seguimiento
            SET si.id_orden_servicio = ss.id_orden_servicio
        ');

        // 3) Creamos FK e índice nuevo sobre id_orden_servicio y orden
        Schema::table('seguimiento_imagenes', function (Blueprint $table) {
            // índice nuevo
            $table->index(['id_orden_servicio', 'orden'], 'seg_img_orden_orden_index');

            // FK nueva hacia orden_servicio
            $table->foreign('id_orden_servicio')
                  ->references('id_orden_servicio')
                  ->on('orden_servicio')
                  ->onDelete('cascade');

            // 4) Eliminamos la relación anterior con seguimiento_servicio
            //    FK + índice + columna id_seguimiento
            $table->dropForeign('seguimiento_imagenes_id_seguimiento_foreign');
            $table->dropIndex('seguimiento_imagenes_id_seguimiento_orden_index');
            $table->dropColumn('id_seguimiento');
        });
    }

    public function down(): void
    {
        // Volvemos al esquema anterior (dependiente de seguimiento_servicio)
        Schema::table('seguimiento_imagenes', function (Blueprint $table) {
            // Recuperamos la columna id_seguimiento
            $table->unsignedBigInteger('id_seguimiento')
                  ->nullable()
                  ->after('id_imagen');
        });

        // Recuperamos datos (de forma aproximada) si existe relación por orden
        // Si hay varios seguimientos por orden, se asignará alguno de ellos.
        DB::statement('
            UPDATE seguimiento_imagenes si
            INNER JOIN seguimiento_servicio ss
                ON si.id_orden_servicio = ss.id_orden_servicio
            SET si.id_seguimiento = ss.id_seguimiento
        ');

        Schema::table('seguimiento_imagenes', function (Blueprint $table) {
            // Índice y FK originales
            $table->index(['id_seguimiento', 'orden'], 'seguimiento_imagenes_id_seguimiento_orden_index');

            $table->foreign('id_seguimiento')
                  ->references('id_seguimiento')
                  ->on('seguimiento_servicio')
                  ->onDelete('cascade');

            // Quitamos la FK/índice/columna nuevos
            $table->dropForeign(['id_orden_servicio']); // nombre por convención
            $table->dropIndex('seg_img_orden_orden_index');
            $table->dropColumn('id_orden_servicio');
        });
    }
};
