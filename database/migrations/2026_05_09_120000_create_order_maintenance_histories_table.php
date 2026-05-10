<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_maintenance_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 40);
            $table->string('reason', 500)->nullable();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot')->nullable();
            $table->timestamps();

            $table->index(['orden_id', 'created_at']);
            $table->foreign('orden_id')
                ->references('id_orden_servicio')
                ->on('orden_servicio')
                ->cascadeOnDelete();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_maintenance_histories');
    }
};
