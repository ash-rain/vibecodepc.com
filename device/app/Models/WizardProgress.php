<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

class WizardProgress extends Model
{
    use HasFactory;

    protected $table = 'wizard_progress';

    protected $fillable = [
        'step',
        'status',
        'data_json',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'step' => WizardStep::class,
            'status' => WizardStepStatus::class,
            'data_json' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->status === WizardStepStatus::Completed;
    }

    public function isSkipped(): bool
    {
        return $this->status === WizardStepStatus::Skipped;
    }

    public function isPending(): bool
    {
        return $this->status === WizardStepStatus::Pending;
    }
}
