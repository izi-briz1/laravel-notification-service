<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_id')->constrained('notifications')->cascadeOnDelete();
            $table->string('status', 16);
            $table->text('info')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_status_history');
    }
};
