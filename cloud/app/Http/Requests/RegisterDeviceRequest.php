<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterDeviceRequest extends FormRequest
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
            'id' => ['required', 'string', 'uuid'],
            'hardware_serial' => ['required', 'string', 'max:255'],
            'firmware_version' => ['required', 'string', 'max:50'],
        ];
    }
}
