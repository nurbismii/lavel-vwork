<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case RevisionRequired = 'revision_required';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Diajukan',
            self::RevisionRequired => 'Perlu Revisi',
            self::Approved => 'Disetujui',
        };
    }
}
