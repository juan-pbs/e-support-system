<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'edit_count')) {
                $table->unsignedInteger('edit_count')->default(0)->after('signature_image');
            }
            if (!Schema::hasColumn('cotizaciones', 'last_edited_at')) {
                $table->timestamp('last_edited_at')->nullable()->after('edit_count');
            }
            if (!Schema::hasColumn('cotizaciones', 'process_count')) {
                $table->unsignedInteger('process_count')->default(0)->after('last_edited_at');
            }
            if (!Schema::hasColumn('cotizaciones', 'last_processed_at')) {
                $table->timestamp('last_processed_at')->nullable()->after('process_count');
            }
            if (!Schema::hasColumn('cotizaciones', 'estado_cotizacion')) {
                $table->string('estado_cotizacion', 50)->default('borrador')->after('last_processed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (Schema::hasColumn('cotizaciones', 'estado_cotizacion'))   $table->dropColumn('estado_cotizacion');
            if (Schema::hasColumn('cotizaciones', 'last_processed_at'))   $table->dropColumn('last_processed_at');
            if (Schema::hasColumn('cotizaciones', 'process_count'))       $table->dropColumn('process_count');
            if (Schema::hasColumn('cotizaciones', 'last_edited_at'))      $table->dropColumn('last_edited_at');
            if (Schema::hasColumn('cotizaciones', 'edit_count'))          $table->dropColumn('edit_count');
        });
    }
};
