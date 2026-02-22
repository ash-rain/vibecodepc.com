<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSubdomainRequest;
use App\Services\CustomDomainService;
use App\Services\SubdomainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubdomainController extends Controller
{
    public function __construct(
        private SubdomainService $subdomainService,
        private CustomDomainService $customDomainService,
    ) {}

    public function edit(Request $request): View
    {
        return view('dashboard.subdomain.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(UpdateSubdomainRequest $request): RedirectResponse
    {
        $this->subdomainService->updateSubdomain(
            $request->user(),
            $request->validated('username'),
        );

        // Handle custom domain if provided
        $customDomain = $request->input('custom_domain');
        $this->customDomainService->setCustomDomain($request->user(), $customDomain);

        return redirect()->route('dashboard.subdomain.edit')
            ->with('status', 'Settings updated successfully.');
    }

    public function verifyDomain(Request $request): RedirectResponse
    {
        $verified = $this->customDomainService->reverify($request->user());

        return redirect()->route('dashboard.subdomain.edit')
            ->with('status', $verified
                ? 'Custom domain verified successfully.'
                : 'CNAME verification failed. Please check your DNS settings.');
    }
}
