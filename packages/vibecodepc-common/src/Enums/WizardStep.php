<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum WizardStep: string
{
    case Welcome = 'welcome';
    case Tunnel = 'tunnel';
    case AiServices = 'ai_services';
    case GitHub = 'github';
    case CodeServer = 'code_server';
    case Complete = 'complete';
}
