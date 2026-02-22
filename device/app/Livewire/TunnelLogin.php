<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\DeviceState;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class TunnelLogin extends Component
{
    public string $password = '';

    public ?string $error = null;

    public function authenticate(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $storedHash = DeviceState::getValue('admin_password_hash');

        if (! $storedHash || ! Hash::check($this->password, $storedHash)) {
            $this->error = 'Invalid password.';
            $this->password = '';

            return;
        }

        session()->put('tunnel_authenticated', true);

        $intended = session()->pull('tunnel_auth_intended_url', route('dashboard'));

        $this->redirect($intended);
    }

    public function render()
    {
        return view('livewire.tunnel-login')
            ->layout('layouts.device')
            ->title('Device Access — VibeCodePC');
    }
}
