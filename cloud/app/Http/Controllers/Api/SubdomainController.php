<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SubdomainService;
use Illuminate\Http\JsonResponse;

class SubdomainController extends Controller
{
    public function __construct(
        private readonly SubdomainService $subdomainService,
    ) {}

    public function availability(string $subdomain): JsonResponse
    {
        if (! preg_match('/^[a-z][a-z0-9-]*[a-z0-9]$/', $subdomain) || strlen($subdomain) < 3 || strlen($subdomain) > 30) {
            return response()->json([
                'available' => false,
                'reason' => 'Invalid subdomain format.',
            ]);
        }

        $available = $this->subdomainService->isAvailable($subdomain);

        return response()->json([
            'available' => $available,
            'reason' => $available ? null : 'This subdomain is already taken or reserved.',
        ]);
    }
}
