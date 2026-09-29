<?php

namespace Database\Seeders;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Enums\WorkType;
use App\Models\ActivityMasterOption;
use App\Models\OrganizationalUnit;
use App\Models\User;
use App\Models\UtilizationThreshold;
use App\Models\WorkloadSubmission;
use App\Models\WorkPeriod;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $unit = OrganizationalUnit::query()->updateOrCreate(
            ['code' => 'OPS'],
            ['name' => 'Operasional', 'is_active' => true],
        );

        $manager = User::query()->updateOrCreate(['email' => 'andi@ruangkerja.test'], [
            'name' => 'Andi Ramadhan',
            'password' => Hash::make('password'),
            'employee_code' => 'OPS-001',
            'position' => 'Manajer Operasional',
            'role' => UserRole::Manager,
            'organizational_unit_id' => $unit->id,
            'is_active' => true,
        ]);

        User::query()->updateOrCreate(['email' => 'admin@ruangkerja.test'], [
            'name' => 'Admin Sistem', 'password' => Hash::make('password'), 'employee_code' => 'ADM-001',
            'position' => 'Administrator', 'role' => UserRole::Administrator, 'organizational_unit_id' => $unit->id, 'is_active' => true,
        ]);

        $period = WorkPeriod::query()->updateOrCreate(['period_start' => '2026-08-01'], [
            'submission_deadline' => '2026-08-26', 'status' => 'open', 'opened_by' => $manager->id, 'opened_at' => now(),
            'standard_work_days' => 20, 'standard_hours_per_day' => 8, 'standard_productive_percentage' => 85,
            'capacity_policy_note' => 'Standar kapasitas untuk periode pilot.',
        ]);

        UtilizationThreshold::query()->updateOrCreate(['effective_from' => '2026-01-01'], [
            'available_below' => 70, 'healthy_up_to' => 85, 'dense_up_to' => 100,
            'is_provisional' => true, 'created_by' => $manager->id, 'change_reason' => 'Ambang awal selama periode pilot.',
        ]);

        ActivityMasterOption::query()->updateOrCreate([
            'type' => ActivityMasterOption::TYPE_CATEGORY, 'normalized_key' => 'operasional',
        ], [
            'value' => 'Operasional', 'label' => 'Operasional', 'is_active' => true, 'created_by' => $manager->id,
        ]);

        $members = [
            ['Dwi Lestari', 'dwi@ruangkerja.test', 'Lead Administrasi', 148, 172, SubmissionStatus::Approved],
            ['Bima Saputra', 'bima@ruangkerja.test', 'Staf Operasional', 152, 159, SubmissionStatus::Submitted],
            ['Nisa Maharani', 'nisa@ruangkerja.test', 'Staf Operasional', 144, 139, SubmissionStatus::Approved],
            ['Rifqi Aditya', 'rifqi@ruangkerja.test', 'Analis Proses', 152, 137, SubmissionStatus::RevisionRequired],
            ['Sari Wulandari', 'sari@ruangkerja.test', 'Staf Administrasi', 148, 123, SubmissionStatus::Approved],
            ['Fajar Nugroho', 'fajar@ruangkerja.test', 'Staf Operasional', 144, 116, SubmissionStatus::Submitted],
            ['Maya Putri', 'maya@ruangkerja.test', 'Staf Administrasi', 148, 115, SubmissionStatus::Approved],
            ['Eko Prasetyo', 'eko@ruangkerja.test', 'Staf Operasional', 148, 85, SubmissionStatus::Approved],
        ];

        foreach ($members as $index => [$name, $email, $position, $capacityHours, $requiredHours, $status]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'employee_code' => sprintf('OPS-%03d', $index + 2),
                'position' => $position,
                'role' => UserRole::Member,
                'organizational_unit_id' => $unit->id,
                'supervisor_id' => $manager->id,
                'is_active' => true,
            ]);

            $submission = WorkloadSubmission::query()->updateOrCreate([
                'work_period_id' => $period->id, 'user_id' => $user->id,
            ], [
                'status' => $status,
                'submitted_at' => $status !== SubmissionStatus::Draft ? now()->subDays(2) : null,
                'reviewed_by' => in_array($status, [SubmissionStatus::Approved, SubmissionStatus::RevisionRequired], true) ? $manager->id : null,
                'reviewed_at' => in_array($status, [SubmissionStatus::Approved, SubmissionStatus::RevisionRequired], true) ? now()->subDay() : null,
                'review_note' => $status === SubmissionStatus::RevisionRequired ? 'Mohon periksa kembali estimasi waktu aktivitas proyek.' : null,
            ]);

            $submission->capacity()->updateOrCreate([], [
                'work_days' => 20,
                'hours_per_day' => 8,
                'productive_percentage' => round(($capacityHours / 160) * 100, 2),
                'gross_minutes' => 160 * 60,
                'effective_minutes' => $capacityHours * 60,
            ]);

            $submission->activities()->updateOrCreate([
                'name' => 'Aktivitas operasional utama', 'work_type' => WorkType::Routine->value,
            ], [
                'category' => 'Operasional', 'monthly_volume' => 1, 'unit' => 'Paket pekerjaan',
                'average_minutes_per_unit' => $requiredHours * 60, 'required_minutes' => $requiredHours * 60,
            ]);
        }

        $this->command?->info('Akun demo: andi@ruangkerja.test / password');
    }
}
