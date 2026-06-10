<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('notification_batches')->cascadeOnDelete();
            // Идентификатор получателя хранится как пришёл от вызывающего
            // сервиса: без валидации формата и без FK (см. ТЗ)
            $table->string('recipient_id');
            $table->string('channel', 16);
            $table->string('priority', 16);
            $table->text('body');
            $table->string('status', 16)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestampTz('dispatched_at')->nullable();
            $table->timestampTz('queued_at');
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'created_at']);
        });

        // Частичный индекс для outbox-свипера: только неопубликованные queued-строки
        DB::statement(
            "CREATE INDEX notifications_outbox_index ON notifications (created_at) WHERE status = 'queued' AND dispatched_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
