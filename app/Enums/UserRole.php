<?php

namespace App\Enums;

enum UserRole: string
{
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Member = 'member';
    case ProcessOwner = 'process_owner';
    case Administrator = 'administrator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Staff',
            self::Manager => 'Manager',
            self::ProcessOwner => 'PIC HR/Operasional',
            self::Administrator => 'Administrator',
            self::Viewer => 'Manajemen/Viewer',
            self::Supervisor => 'Supervisor',
        };
    }
}
