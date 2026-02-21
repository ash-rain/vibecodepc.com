<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use VibecodePC\Common\Enums\ProjectFramework;
use VibecodePC\Common\Enums\ProjectStatus;

/** @extends Factory<Project> */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'framework' => fake()->randomElement(ProjectFramework::cases()),
            'status' => ProjectStatus::Created,
            'path' => '/home/vibecodepc/projects/'.Str::slug($name),
            'port' => fake()->numberBetween(3000, 9000),
            'container_id' => null,
            'tunnel_subdomain_path' => null,
            'tunnel_enabled' => false,
            'env_vars' => null,
            'last_started_at' => null,
            'last_stopped_at' => null,
        ];
    }

    public function running(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Running,
            'container_id' => 'container_'.fake()->sha1(),
            'last_started_at' => now(),
        ]);
    }

    public function stopped(): static
    {
        return $this->state(fn () => [
            'status' => ProjectStatus::Stopped,
            'last_stopped_at' => now(),
        ]);
    }

    public function forFramework(ProjectFramework $framework): static
    {
        return $this->state(fn () => [
            'framework' => $framework,
            'port' => $framework->defaultPort(),
        ]);
    }
}
