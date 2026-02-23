<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw DB query (not Eloquent) to avoid WizardStep enum cast
        // failure now that the Tunnel case has been removed.
        DB::table('wizard_progress')->where('step', 'tunnel')->delete();
    }

    public function down(): void
    {
        DB::table('wizard_progress')->insertOrIgnore([
            'step' => 'tunnel',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
