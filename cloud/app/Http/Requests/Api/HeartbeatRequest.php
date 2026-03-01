<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class HeartbeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'cpu_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cpu_temp' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'ram_used_mb' => ['nullable', 'integer', 'min:0'],
            'ram_total_mb' => ['nullable', 'integer', 'min:0'],
            'disk_used_gb' => ['nullable', 'numeric', 'min:0'],
            'disk_total_gb' => ['nullable', 'numeric', 'min:0'],
            'running_projects' => ['nullable', 'integer', 'min:0'],
            'tunnel_active' => ['nullable', 'boolean'],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:100'],
            'quick_tunnels' => ['nullable', 'array', 'max:20'],
            'quick_tunnels.*.tunnel_url' => ['required', 'string', 'url', 'max:500'],
            'quick_tunnels.*.local_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'quick_tunnels.*.project_name' => ['nullable', 'string', 'max:255'],
            'quick_tunnels.*.status' => ['required', 'string', 'in:starting,running'],
            'quick_tunnels.*.started_at' => ['nullable', 'string', 'max:30'],
        ];
    }
}
