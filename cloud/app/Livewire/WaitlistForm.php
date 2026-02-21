<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Lead;
use Livewire\Attributes\Validate;
use Livewire\Component;

class WaitlistForm extends Component
{
    #[Validate('required|email|max:255|unique:leads,email')]
    public string $email = '';

    public bool $submitted = false;

    public function submit(): void
    {
        $this->validate();

        Lead::create([
            'email' => $this->email,
            'source' => 'landing_page',
            'ip_address' => request()->ip(),
        ]);

        $this->submitted = true;
        $this->email = '';
    }

    public function render()
    {
        return view('livewire.waitlist-form');
    }
}
