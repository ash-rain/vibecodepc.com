<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum ProjectStatus: string
{
    case Scaffolding = 'scaffolding';
    case Cloning = 'cloning';
    case Created = 'created';
    case Running = 'running';
    case Stopped = 'stopped';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Scaffolding => 'Scaffolding',
            self::Cloning => 'Cloning',
            self::Created => 'Created',
            self::Running => 'Running',
            self::Stopped => 'Stopped',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scaffolding, self::Cloning => 'blue',
            self::Created => 'gray',
            self::Running => 'green',
            self::Stopped => 'amber',
            self::Error => 'red',
        };
    }
}
