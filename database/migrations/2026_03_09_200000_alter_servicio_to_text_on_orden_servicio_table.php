<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orden_servicio') || !Schema::hasColumn('orden_servicio', 'servicio')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE orden_servicio MODIFY servicio TEXT NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orden_servicio ALTER COLUMN servicio TYPE TEXT');
            DB::statement('ALTER TABLE orden_servicio ALTER COLUMN servicio DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('orden_servicio') || !Schema::hasColumn('orden_servicio', 'servicio')) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE orden_servicio MODIFY servicio VARCHAR(255) NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE orden_servicio ALTER COLUMN servicio TYPE VARCHAR(255)');
            DB::statement('ALTER TABLE orden_servicio ALTER COLUMN servicio DROP NOT NULL');
        }
    }
};
