<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Ajusta BIGINT/INT y UNSIGNED según tu tabla real
        DB::statement("
            ALTER TABLE seguimiento_servicio
            MODIFY COLUMN id_seguimiento BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE seguimiento_servicio
            MODIFY COLUMN id_seguimiento BIGINT UNSIGNED NOT NULL
        ");
    }
};
