<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Project;
use Illuminate\Support\Facades\Cache;

class ProjectObserver
{
    private const USED_PORTS_CACHE_KEY = 'projects.used_ports';

    /**
     * Handle the Project "created" event.
     */
    public function created(Project $project): void
    {
        $this->clearUsedPortsCache();
    }

    /**
     * Handle the Project "updated" event.
     *
     * Only clears cache if the port field was changed.
     */
    public function updated(Project $project): void
    {
        if ($project->wasChanged('port')) {
            $this->clearUsedPortsCache();
        }
    }

    /**
     * Handle the Project "deleted" event.
     */
    public function deleted(Project $project): void
    {
        $this->clearUsedPortsCache();
    }

    /**
     * Handle the Project "restored" event.
     */
    public function restored(Project $project): void
    {
        $this->clearUsedPortsCache();
    }

    /**
     * Handle the Project "force deleted" event.
     */
    public function forceDeleted(Project $project): void
    {
        $this->clearUsedPortsCache();
    }

    /**
     * Clear the used ports cache.
     */
    private function clearUsedPortsCache(): void
    {
        Cache::forget(self::USED_PORTS_CACHE_KEY);
    }
}
