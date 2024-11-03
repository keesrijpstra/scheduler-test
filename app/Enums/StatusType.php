<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StatusType: string implements HasLabel, HasIcon, HasColor
{
    case Open = 'open';
    case In_progress = 'in_progress';
    case Completed = 'completed';
    case On_hold = 'on_hold';

    public function getLabel(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::In_progress => 'In Progress',
            self::Completed => 'Completed',
            self::On_hold => 'On Hold',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Open => 'heroicon-o-document',
            self::In_progress => 'heroicon-o-clock',
            self::Completed => 'heroicon-o-check-circle',
            self::On_hold => 'heroicon-o-pause',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'primary',
            self::In_progress => 'blue',
            self::Completed => 'warning',
            self::On_hold => 'info',
        };
    }
}
