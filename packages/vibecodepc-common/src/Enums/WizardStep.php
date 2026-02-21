<?php

declare(strict_types=1);

namespace VibecodePC\Common\Enums;

enum WizardStep: string
{
    case Welcome = 'welcome';
    case AiServices = 'ai_services';
    case GitHub = 'github';
    case CodeServer = 'code_server';
    case Tunnel = 'tunnel';
    case Complete = 'complete';
}
