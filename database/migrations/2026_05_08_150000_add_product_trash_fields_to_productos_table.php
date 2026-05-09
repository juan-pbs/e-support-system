<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'deleted_at')) {
                $table->softDeletes();
            }

            if (! Schema::hasColumn('productos', 'deleted_by')) {
                $table->unsignedBigInteger('deleted_by')->nullable()->after('deleted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (Schema::hasColumn('productos', 'deleted_by')) {
                $table->dropColumn('deleted_by');
            }

            if (Schema::hasColumn('productos', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
