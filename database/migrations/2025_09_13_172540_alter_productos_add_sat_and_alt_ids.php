<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (!Schema::hasColumn('productos','numero_parte')) {
                $table->string('numero_parte', 80)->nullable()->unique()->after('nombre');
            }
            if (!Schema::hasColumn('productos','num_identificacion')) {
                $table->string('num_identificacion', 100)->nullable()->after('numero_parte');
            }
            if (!Schema::hasColumn('productos','clave_prodserv')) {
                $table->string('clave_prodserv', 10)->nullable()->after('categoria'); // SAT 6-8 chars
            }
            if (!Schema::hasColumn('productos','clave_unidad')) {
                $table->string('clave_unidad', 10)->nullable()->after('clave_prodserv'); // SAT 3-5 chars
            }
            if (!Schema::hasColumn('productos','unidad')) {
                $table->string('unidad', 30)->nullable()->after('clave_unidad'); // p. ej. "pieza"
            }
            if (!Schema::hasColumn('productos','unidad_desc')) {
                $table->string('unidad_desc', 50)->nullable()->after('unidad'); // p. ej. "Pieza"
            }
            if (!Schema::hasColumn('productos','stock_seguridad')) {
                $table->unsignedInteger('stock_seguridad')->default(0)->after('unidad_desc');
            }
            if (!Schema::hasColumn('productos','activo')) {
                $table->boolean('activo')->default(true)->after('stock_seguridad');
            }
            // Opcional si no existen en tu schema:
            if (!Schema::hasColumn('productos','categoria')) {
                $table->string('categoria', 100)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('productos','descripcion')) {
                $table->text('descripcion')->nullable()->after('categoria');
            }
            if (!Schema::hasColumn('productos','imagen')) {
                $table->string('imagen', 255)->nullable()->after('descripcion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            // Revertir solo si lo necesitas
            $cols = ['num_identificacion','clave_prodserv','clave_unidad','unidad','unidad_desc'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('productos',$c)) $table->dropColumn($c);
            }
        });
    }
};
