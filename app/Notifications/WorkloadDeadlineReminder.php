<?php

namespace App\Notifications;

use App\Models\WorkPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkloadDeadlineReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly WorkPeriod $period) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengingat pengisian beban kerja '.$this->period->period_start->translatedFormat('F Y'))
            ->greeting('Halo, '.$notifiable->name)
            ->line('Data beban kerja bulanan Anda belum lengkap atau masih perlu direvisi.')
            ->line('Batas pengisian: '.$this->period->submission_deadline->translatedFormat('d F Y').'.')
            ->action('Lengkapi data sekarang', route('workload.entry'))
            ->line('Utilisasi digunakan untuk diagnosis kapasitas, bukan sebagai satu-satunya ukuran kinerja.');
    }
}
