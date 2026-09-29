<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workload_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('reminder_date');
            $table->string('channel', 20)->default('mail');
            $table->string('status', 20)->default('queued');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
            $table->unique(['work_period_id', 'user_id', 'reminder_date', 'channel'], 'workload_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workload_reminder_deliveries');
    }
};
