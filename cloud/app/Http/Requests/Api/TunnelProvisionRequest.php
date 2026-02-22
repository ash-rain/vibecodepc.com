<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class TunnelProvisionRequest extends FormRequest
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
            'subdomain' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9-]*[a-z0-9]$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'Subdomain must start with a letter, use lowercase alphanumeric and hyphens only, and end with a letter or number.',
        ];
    }
}
