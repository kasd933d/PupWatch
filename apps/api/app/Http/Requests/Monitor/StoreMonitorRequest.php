<?php

namespace App\Http\Requests\Monitor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return MonitorRules::rules();
    }

    /** @return array<string, callable(Validator): void> */
    public function after(): array
    {
        $user = $this->user();

        return MonitorRules::after(fn () => $user?->monitors()->count() ?? 0);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'url.url' => 'The URL must start with http:// or https://.',
            'expected_status.regex' => 'Expected status must look like "200-399", or a list like "200,204,301-302".',
        ];
    }
}
