<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('google_calendar_accounts')) {
            Schema::create('google_calendar_accounts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('google_email')->nullable();
                $table->string('google_sub')->nullable();
                $table->string('calendar_id')->default('primary');
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->json('scopes')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->boolean('sync_enabled')->default(true);
                $table->timestamp('connected_at')->nullable();
                $table->timestamp('disconnected_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('google_calendar_order_events')) {
            Schema::create('google_calendar_order_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('google_calendar_account_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('orden_servicio_id');
                $table->string('calendar_id')->default('primary');
                $table->string('event_id');
                $table->string('payload_hash', 64)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();

                $table->unique(['google_calendar_account_id', 'orden_servicio_id'], 'gcal_account_order_unique');

                $table->foreign('google_calendar_account_id', 'gcal_order_events_account_fk')
                    ->references('id')
                    ->on('google_calendar_accounts')
                    ->cascadeOnDelete();
                $table->foreign('user_id', 'gcal_order_events_user_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
                $table->foreign('orden_servicio_id', 'gcal_order_events_order_fk')
                    ->references('id_orden_servicio')
                    ->on('orden_servicio')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_order_events');
        Schema::dropIfExists('google_calendar_accounts');
    }
};
