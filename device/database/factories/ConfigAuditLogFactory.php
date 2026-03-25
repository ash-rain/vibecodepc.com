<?php

namespace Database\Factories;

use App\Models\ConfigAuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConfigAuditLogFactory extends Factory
{
    protected $model = ConfigAuditLog::class;

    public function definition(): array
    {
        return [
            'config_key' => $this->faker->randomElement(['boost', 'opencode_global', 'claude_global', 'copilot_instructions']),
            'action' => $this->faker->randomElement(['save', 'delete', 'restore', 'reset']),
            'user_id' => null,
            'project_id' => null,
            'file_path' => '/path/to/config.json',
            'old_content_hash' => null,
            'new_content_hash' => ['sha256' => hash('sha256', 'new content')],
            'backup_path' => null,
            'change_summary' => 'Updated configuration',
            'ip_address' => $this->faker->ipv4,
            'user_agent' => $this->faker->userAgent,
        ];
    }

    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
        ]);
    }

    public function withProject(): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => Project::factory(),
        ]);
    }

    public function saveAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'save',
            'change_summary' => 'Updated configuration',
        ]);
    }

    public function deleteAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'delete',
            'new_content_hash' => null,
            'change_summary' => 'Deleted configuration file',
        ]);
    }

    public function restoreAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'restore',
            'backup_path' => '/backups/config-2026-01-01-120000.json',
            'change_summary' => 'Restored from backup',
        ]);
    }

    public function resetAction(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'reset',
            'change_summary' => 'Reset to default values',
        ]);
    }
}
