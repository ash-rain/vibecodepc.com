<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use RuntimeException;
use VibecodePC\Common\Enums\ProjectFramework;

class PortAllocatorService
{
    private const MIN_PORT = 1024;

    private const MAX_PORT = 65535;

    public function allocate(ProjectFramework $framework): int
    {
        $port = $framework->defaultPort();

        // Ensure starting port is within valid range
        if ($port < self::MIN_PORT) {
            $port = self::MIN_PORT;
        }

        if ($port > self::MAX_PORT) {
            throw new RuntimeException(
                "Framework default port {$port} exceeds maximum allowed port ".self::MAX_PORT
            );
        }

        $usedPorts = Project::pluck('port')->filter()->all();

        // Find next available port within valid range
        while (in_array($port, $usedPorts, true)) {
            $port++;

            // Check for port exhaustion
            if ($port > self::MAX_PORT) {
                throw new RuntimeException(
                    'No available ports in range '.self::MIN_PORT.'-'.self::MAX_PORT
                );
            }
        }

        return $port;
    }
}
