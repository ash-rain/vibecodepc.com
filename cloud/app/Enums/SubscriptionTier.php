<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionTier: string
{
    case Free = 'free';
    case Starter = 'starter';
    case Pro = 'pro';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Starter => 'Starter',
            self::Pro => 'Pro',
            self::Team => 'Team',
        };
    }

    public function maxSubdomains(): int
    {
        return match ($this) {
            self::Free => 1,
            self::Starter => 3,
            self::Pro => 10,
            self::Team => 50,
        };
    }

    public function monthlyBandwidthGb(): int
    {
        return match ($this) {
            self::Free => 1,
            self::Starter => 10,
            self::Pro => 50,
            self::Team => 200,
        };
    }

    public function price(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Starter => 5,
            self::Pro => 15,
            self::Team => 39,
        };
    }

    public function stripePriceId(): ?string
    {
        return match ($this) {
            self::Free => null,
            self::Starter => config('services.stripe.prices.starter'),
            self::Pro => config('services.stripe.prices.pro'),
            self::Team => config('services.stripe.prices.team'),
        };
    }

    public function canUseTunnel(): bool
    {
        return true;
    }
}
