<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_master_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30)->index();
            $table->string('value', 100);
            $table->string('label', 100);
            $table->string('normalized_key', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['type', 'normalized_key'], 'activity_master_type_normalized_unique');
            $table->unique(['type', 'value'], 'activity_master_type_value_unique');
        });

        Schema::table('workload_activities', function (Blueprint $table) {
            $table->string('work_type', 100)->change();
        });

        $now = now();
        DB::table('activity_master_options')->insert([
            ['type' => 'work_type', 'value' => 'routine', 'label' => 'Rutin', 'normalized_key' => 'rutin', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'work_type', 'value' => 'project', 'label' => 'Proyek', 'normalized_key' => 'proyek', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['type' => 'work_type', 'value' => 'urgent', 'label' => 'Mendadak', 'normalized_key' => 'mendadak', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        DB::table('workload_activities')->select('category')->distinct()->orderBy('category')->get()
            ->each(function ($row) use ($now) {
                $label = Str::of($row->category)->squish()->toString();
                $normalized = Str::of(Str::ascii($label))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->toString();

                DB::table('activity_master_options')->insertOrIgnore([
                    'type' => 'category', 'value' => $label, 'label' => $label,
                    'normalized_key' => $normalized, 'is_active' => true,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_master_options');

        Schema::table('workload_activities', function (Blueprint $table) {
            $table->string('work_type', 20)->change();
        });
    }
};
