<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\TunnelRoute */
class TunnelRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subdomain' => $this->subdomain,
            'path' => $this->path,
            'target_port' => $this->target_port,
            'project_name' => $this->project_name,
            'is_active' => $this->is_active,
            'full_url' => $this->full_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
