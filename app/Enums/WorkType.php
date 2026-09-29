<?php

namespace App\Enums;

enum WorkType: string
{
    case Routine = 'routine';
    case Project = 'project';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Routine => 'Rutin',
            self::Project => 'Proyek',
            self::Urgent => 'Mendadak',
        };
    }
}
