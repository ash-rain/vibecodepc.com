<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum ProjectStatus: string
{
    case Created = 'created';
    case Running = 'running';
    case Stopped = 'stopped';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Created => 'gray',
            self::Running => 'green',
            self::Stopped => 'amber',
            self::Error => 'red',
        };
    }
}
