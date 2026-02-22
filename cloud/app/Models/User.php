<?php

namespace App\Models;

use App\Enums\SubscriptionTier;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use Billable, HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'is_admin',
        'custom_domain',
        'custom_domain_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'custom_domain_verified_at' => 'datetime',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    public function subscriptionTier(): SubscriptionTier
    {
        if ($this->subscribed('default')) {
            $subscription = $this->subscription('default');

            foreach ([SubscriptionTier::Team, SubscriptionTier::Pro, SubscriptionTier::Starter] as $tier) {
                if ($subscription->hasPrice($tier->stripePriceId())) {
                    return $tier;
                }
            }
        }

        return SubscriptionTier::Free;
    }

    public function canUseTunnel(): bool
    {
        return $this->subscriptionTier()->canUseTunnel();
    }

    public function maxSubdomains(): int
    {
        return $this->subscriptionTier()->maxSubdomains();
    }
}
