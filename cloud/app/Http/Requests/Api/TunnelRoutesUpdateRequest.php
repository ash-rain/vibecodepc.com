<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TunnelRoutesUpdateRequest extends FormRequest
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
            'subdomain' => ['required', 'string', 'max:63'],
            'routes' => ['required', 'array', 'min:1'],
            'routes.*.path' => ['required', 'string', 'max:255'],
            'routes.*.target_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'routes.*.project_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
