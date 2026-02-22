<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\SubdomainService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubdomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/^[a-z][a-z0-9-]*[a-z0-9]$/',
                'unique:users,username,'.$this->user()->id,
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (app(SubdomainService::class)->isReserved($value)) {
                        $fail('This subdomain is reserved and cannot be used.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.regex' => 'Subdomain must start with a letter, contain only lowercase letters, numbers, and hyphens, and end with a letter or number.',
            'username.unique' => 'This subdomain is already taken.',
        ];
    }
}
