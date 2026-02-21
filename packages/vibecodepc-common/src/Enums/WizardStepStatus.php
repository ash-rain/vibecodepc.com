<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum WizardStepStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
