<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use VibecodePC\Common\Enums\ProjectFramework;

class PortAllocatorService
{
    public function allocate(ProjectFramework $framework): int
    {
        $port = $framework->defaultPort();
        $usedPorts = Project::pluck('port')->filter()->all();

        while (in_array($port, $usedPorts, true)) {
            $port++;
        }

        return $port;
    }
}
