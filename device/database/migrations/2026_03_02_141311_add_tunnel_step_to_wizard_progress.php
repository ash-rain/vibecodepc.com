<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use VibecodePC\Common\Enums\WizardStep;
use VibecodePC\Common\Enums\WizardStepStatus;

return new class extends Migration
{
    public function up(): void
    {
        $existingSteps = DB::table('wizard_progress')->pluck('step')->toArray();

        foreach (WizardStep::cases() as $step) {
            if (! in_array($step->value, $existingSteps)) {
                DB::table('wizard_progress')->insert([
                    'step' => $step->value,
                    'status' => WizardStepStatus::Pending->value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('wizard_progress')->where('step', 'tunnel')->delete();
    }
};
