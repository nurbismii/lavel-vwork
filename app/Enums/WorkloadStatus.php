<?php

namespace App\Enums;

enum WorkloadStatus: string
{
    case Available = 'available';
    case Healthy = 'healthy';
    case Dense = 'dense';
    case Overload = 'overload';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Kapasitas tersedia',
            self::Healthy => 'Sehat',
            self::Dense => 'Padat',
            self::Overload => 'Kelebihan beban',
            self::Unavailable => 'Kapasitas belum tersedia',
        };
    }
}
