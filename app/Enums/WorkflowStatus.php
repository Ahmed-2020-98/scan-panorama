<?php

namespace App\Enums;

enum WorkflowStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديدة', self::InProgress => 'قيد التنفيذ', self::Completed => 'مكتملة'
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'sky', self::InProgress => 'amber', self::Completed => 'green'
        };
    }
}
