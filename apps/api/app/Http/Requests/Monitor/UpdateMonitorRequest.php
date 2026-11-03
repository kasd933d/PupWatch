<?php

namespace App\Http\Requests\Monitor;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('monitor')) ?? false;
    }

    /**
     * Shared rules, made optional so PATCH works as a partial update.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = MonitorRules::rules();

        foreach ($rules as $key => $rule) {
            $rules[$key] = array_values(array_merge(['sometimes'], $rule));
        }

        return $rules;
    }

    /** @return array<string, callable(Validator): void> */
    public function after(): array
    {
        // Editing an existing monitor never counts towards the plan limit.
        return MonitorRules::after(fn () => 0);
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
