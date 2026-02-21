<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum DeviceStatus: string
{
    case Unclaimed = 'unclaimed';
    case Claimed = 'claimed';
    case Deactivated = 'deactivated';
}
