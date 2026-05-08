<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255)->unique();
            $table->timestamps();
        });

        $base = [
            'hardware',
            'software',
            'perifericos',
            'componentes',
            'redes',
            'accesorios',
            'otra',
        ];

        foreach ($base as $categoria) {
            DB::table('categorias_productos')->insert([
                'nombre' => $categoria,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('productos')) {
            $categorias = DB::table('productos')
                ->whereNotNull('categoria')
                ->select('categoria')
                ->distinct()
                ->pluck('categoria');

            foreach ($categorias as $categoria) {
                $nombre = trim((string) $categoria);
                if ($nombre === '') {
                    continue;
                }

                DB::table('categorias_productos')->updateOrInsert(
                    ['nombre' => $nombre],
                    ['updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_productos');
    }
};
