<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\WorkloadReminderDelivery;
use App\Models\WorkPeriod;
use App\Notifications\WorkloadDeadlineReminder;
use Illuminate\Console\Command;

class SendWorkloadReminders extends Command
{
    protected $signature = 'workload:send-reminders {--period= : ID periode tertentu}';

    protected $description = 'Antrekan pengingat untuk anggota dengan data beban kerja yang belum lengkap';

    public function handle(): int
    {
        $periods = WorkPeriod::query()->where('status', 'open')
            ->when($this->option('period'), fn ($query) => $query->whereKey($this->option('period')))
            ->whereDate('submission_deadline', '>=', today())
            ->whereDate('submission_deadline', '<=', today()->addDays(5))
            ->get();
        $queued = 0;

        foreach ($periods as $period) {
            $users = User::query()->where('is_active', true)->where('role', UserRole::Member)
                ->whereDoesntHave('workloadSubmissions', fn ($query) => $query
                    ->where('work_period_id', $period->id)
                    ->whereIn('status', [SubmissionStatus::Submitted, SubmissionStatus::Approved]))
                ->get();

            foreach ($users as $user) {
                $delivery = WorkloadReminderDelivery::query()->firstOrCreate([
                    'work_period_id' => $period->id,
                    'user_id' => $user->id,
                    'reminder_date' => today(),
                    'channel' => 'mail',
                ], ['status' => 'queued', 'queued_at' => now()]);

                if ($delivery->wasRecentlyCreated) {
                    $user->notify(new WorkloadDeadlineReminder($period));
                    $queued++;
                }
            }
        }

        $this->info("{$queued} pengingat dimasukkan ke antrean.");

        return self::SUCCESS;
    }
}
