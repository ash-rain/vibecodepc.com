<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

class CustomDomainService
{
    /**
     * Verify that the custom domain has a CNAME pointing to the user's subdomain.
     */
    public function verifyCname(User $user, string $domain): bool
    {
        $expectedTarget = $user->username.'.vibecodepc.com';
        $records = @dns_get_record($domain, DNS_CNAME);

        if (! $records) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['target']) && rtrim($record['target'], '.') === $expectedTarget) {
                return true;
            }
        }

        return false;
    }

    /**
     * Set and optionally verify a custom domain for the user.
     */
    public function setCustomDomain(User $user, ?string $domain): void
    {
        if ($domain === null || $domain === '') {
            $user->update([
                'custom_domain' => null,
                'custom_domain_verified_at' => null,
            ]);

            return;
        }

        $domain = strtolower(trim($domain));
        $verified = $this->verifyCname($user, $domain);

        $user->update([
            'custom_domain' => $domain,
            'custom_domain_verified_at' => $verified ? now() : null,
        ]);
    }

    /**
     * Re-verify an existing custom domain.
     */
    public function reverify(User $user): bool
    {
        if (! $user->custom_domain) {
            return false;
        }

        $verified = $this->verifyCname($user, $user->custom_domain);

        $user->update([
            'custom_domain_verified_at' => $verified ? now() : null,
        ]);

        return $verified;
    }

    /**
     * Resolve a custom domain to a username.
     */
    public function resolveToUsername(string $host): ?string
    {
        $user = User::query()
            ->where('custom_domain', strtolower($host))
            ->whereNotNull('custom_domain_verified_at')
            ->first();

        return $user?->username;
    }
}
