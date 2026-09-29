<?php

namespace App\Enums;

enum ProgressStatus: string
{
    case Completed = 'completed';
    case InProgress = 'in_progress';
    case Planned = 'planned';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai dikerjakan',
            self::InProgress => 'Sedang dikerjakan',
            self::Planned => 'Akan dikerjakan',
        };
    }
}
