<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organizational_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 50)->nullable()->unique();
            $table->string('position')->nullable();
            $table->string('role', 30)->default('member')->index();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('work_periods', function (Blueprint $table) {
            $table->id();
            $table->date('period_start')->unique();
            $table->date('submission_deadline');
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('utilization_thresholds', function (Blueprint $table) {
            $table->id();
            $table->decimal('available_below', 5, 2)->default(70);
            $table->decimal('healthy_up_to', 5, 2)->default(85);
            $table->decimal('dense_up_to', 5, 2)->default(100);
            $table->date('effective_from')->unique();
            $table->boolean('is_provisional')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('workload_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft')->index();
            $table->text('member_note')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['work_period_id', 'user_id']);
            $table->index(['work_period_id', 'status']);
        });

        Schema::create('workload_capacities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workload_submission_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('work_days');
            $table->decimal('hours_per_day', 4, 2);
            $table->decimal('productive_percentage', 5, 2);
            $table->unsignedInteger('gross_minutes');
            $table->unsignedInteger('effective_minutes');
            $table->timestamps();
        });

        Schema::create('workload_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workload_submission_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('name');
            $table->string('work_type', 20)->index();
            $table->decimal('monthly_volume', 12, 2);
            $table->string('unit', 50);
            $table->decimal('average_minutes_per_unit', 10, 2);
            $table->unsignedInteger('required_minutes');
            $table->text('exception_reason')->nullable();
            $table->timestamps();
            $table->index(['workload_submission_id', 'work_type']);
            $table->unique(['workload_submission_id', 'name', 'work_type'], 'workload_activity_unique');
        });

        Schema::create('validation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workload_submission_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('note')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('follow_up_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workload_submission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organizational_unit_id')->constrained()->cascadeOnDelete();
            $table->string('action_type', 40);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->date('target_date')->nullable();
            $table->string('status', 20)->default('open')->index();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('follow_up_actions');
        Schema::dropIfExists('validation_histories');
        Schema::dropIfExists('workload_activities');
        Schema::dropIfExists('workload_capacities');
        Schema::dropIfExists('workload_submissions');
        Schema::dropIfExists('utilization_thresholds');
        Schema::dropIfExists('work_periods');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('organizational_unit_id');
            $table->dropColumn(['employee_code', 'position', 'role', 'is_active']);
        });
        Schema::dropIfExists('organizational_units');
    }
};
