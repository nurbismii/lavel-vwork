<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_progress_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workload_submission_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('category', 100);
            $table->string('name');
            $table->string('status', 20)->index();
            $table->text('progress_summary');
            $table->text('obstacle_note')->nullable();
            $table->text('action_note');
            $table->date('target_date')->nullable();
            $table->unsignedTinyInteger('progress_percentage');
            $table->timestamps();

            $table->index(['workload_submission_id', 'report_date'], 'work_progress_submission_date_index');
            $table->index(['workload_submission_id', 'category', 'name'], 'work_progress_job_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_progress_items');
    }
};
