<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Cache;
use VibecodePC\Common\Enums\ProjectStatus;

class ProjectRepository
{
    private const USED_PORTS_CACHE_KEY = 'projects.used_ports';

    private const USED_PORTS_CACHE_TTL_SECONDS = 60;

    /**
     * Find a project by ID.
     */
    public function find(int $id): ?Project
    {
        return Project::find($id);
    }

    /**
     * Find a project by ID or throw ModelNotFoundException.
     */
    public function findOrFail(int $id): Project
    {
        return Project::findOrFail($id);
    }

    /**
     * Get all projects.
     *
     * @return Collection<int, Project>
     */
    public function all(): Collection
    {
        return Project::all();
    }

    /**
     * Get all projects with specific columns.
     *
     * @return Collection<int, Project>
     */
    public function allWithColumns(array $columns): Collection
    {
        return Project::all($columns);
    }

    /**
     * Get projects by IDs.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Project>
     */
    public function getByIds(array $ids): Collection
    {
        return Project::whereIn('id', $ids)->get();
    }

    /**
     * Get projects by IDs keyed by ID.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Project>
     */
    public function getByIdsKeyed(array $ids): Collection
    {
        return Project::whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * Check if a project exists by name.
     */
    public function existsByName(string $name): bool
    {
        return Project::where('name', $name)->exists();
    }

    /**
     * Get total count of projects.
     */
    public function count(): int
    {
        return Project::count();
    }

    /**
     * Count projects by status.
     */
    public function countByStatus(ProjectStatus $status): int
    {
        return Project::where('status', $status)->count();
    }

    /**
     * Count projects where status is in the given array.
     *
     * @param  array<ProjectStatus>  $statuses
     */
    public function countWhereStatusIn(array $statuses): int
    {
        return Project::whereIn('status', $statuses)->count();
    }

    /**
     * Get all used ports as an array.
     *
     * Caches the result for 60 seconds to reduce database load during
     * high-frequency port allocations. Cache is cleared when projects
     * are created, updated, or deleted via model events.
     *
     * @return array<int>
     */
    public function getUsedPorts(): array
    {
        return Cache::remember(
            self::USED_PORTS_CACHE_KEY,
            self::USED_PORTS_CACHE_TTL_SECONDS,
            fn (): array => Project::pluck('port')
                ->filter()
                ->all()
        );
    }

    /**
     * Clear the used ports cache.
     *
     * Called automatically when projects are created, updated, or deleted
     * via model observers to ensure cache stays synchronized with database.
     */
    public function clearUsedPortsCache(): void
    {
        Cache::forget(self::USED_PORTS_CACHE_KEY);
    }

    /**
     * Get projects with cursor pagination.
     */
    public function paginateLatest(int $perPage = 10, ?Cursor $cursor = null): CursorPaginator
    {
        if ($cursor === null) {
            return Project::latest()->cursorPaginate($perPage);
        }

        return Project::latest()->cursorPaginate($perPage, ['*'], 'cursor', $cursor);
    }

    /**
     * Get all projects with specific columns for tunnel display.
     *
     * @return Collection<int, Project>
     */
    public function allForTunnelDisplay(): Collection
    {
        return Project::query()
            ->select(['id', 'name', 'slug', 'port', 'tunnel_enabled', 'tunnel_subdomain_path'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Get projects with tunnel enabled.
     *
     * @return Collection<int, Project>
     */
    public function getWithTunnelEnabled(): Collection
    {
        return Project::where('tunnel_enabled', true)->get();
    }

    /**
     * Find abandoned projects for cleanup.
     *
     * @return Collection<int, Project>
     */
    public function findAbandonedProjects(\DateTimeInterface $cutoffDate): Collection
    {
        return Project::query()
            ->where(function (Builder $query) use ($cutoffDate): void {
                $query->where('status', ProjectStatus::Error)
                    ->orWhere(function (Builder $q) use ($cutoffDate): void {
                        $q->where('status', ProjectStatus::Created)
                            ->whereNull('last_started_at')
                            ->where('created_at', '<', $cutoffDate);
                    })
                    ->orWhere(function (Builder $q) use ($cutoffDate): void {
                        $q->where('status', ProjectStatus::Stopped)
                            ->where(function (Builder $q2) use ($cutoffDate): void {
                                $q2->whereNotNull('last_stopped_at')
                                    ->where('last_stopped_at', '<', $cutoffDate);
                            });
                    });
            })
            ->get();
    }

    /**
     * Get projects with running containers.
     *
     * @return Collection<int, Project>
     */
    public function getRunning(): Collection
    {
        return Project::where('status', ProjectStatus::Running)->get();
    }

    /**
     * Get projects that are not running (stopped, created, scaffolding, cloning).
     *
     * @return Collection<int, Project>
     */
    public function getNonRunning(): Collection
    {
        return Project::whereIn('status', [
            ProjectStatus::Stopped,
            ProjectStatus::Created,
            ProjectStatus::Scaffolding,
            ProjectStatus::Cloning,
        ])->get();
    }

    /**
     * Get projects with error status.
     *
     * @return Collection<int, Project>
     */
    public function getWithErrors(): Collection
    {
        return Project::where('status', ProjectStatus::Error)->get();
    }

    /**
     * Delete projects where status is in the given array.
     *
     * @param  array<ProjectStatus>  $statuses
     */
    public function deleteWhereStatusIn(array $statuses): void
    {
        Project::whereIn('status', $statuses)->delete();
    }

    /**
     * Update a project by ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        $project = $this->find($id);

        if ($project === null) {
            return false;
        }

        return $project->update($data);
    }

    /**
     * Create a new project.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Project
    {
        return Project::create($data);
    }

    /**
     * Delete a project by ID.
     */
    public function delete(int $id): bool
    {
        $project = $this->find($id);

        if ($project === null) {
            return false;
        }

        return $project->delete();
    }

    /**
     * Get the query builder for custom queries.
     */
    public function query(): Builder
    {
        return Project::query();
    }

    /**
     * Get projects for tunnel routes (tunnel enabled with subdomain and port).
     *
     * @return array<string, int>
     */
    public function getTunnelRoutes(): array
    {
        return Project::where('tunnel_enabled', true)
            ->whereNotNull('tunnel_subdomain_path')
            ->whereNotNull('port')
            ->pluck('port', 'tunnel_subdomain_path')
            ->all();
    }

    /**
     * Get all projects ordered by latest first.
     *
     * @return Collection<int, Project>
     */
    public function getLatest(): Collection
    {
        return Project::latest()->get();
    }

    /**
     * Count running projects.
     */
    public function countRunning(): int
    {
        return Project::running()->count();
    }

    /**
     * Get projects with only name and env_vars columns.
     *
     * @return Collection<int, Project>
     */
    public function getAllWithEnvVars(): Collection
    {
        return Project::all(['name', 'env_vars']);
    }
}
