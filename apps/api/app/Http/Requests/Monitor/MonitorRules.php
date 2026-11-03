<?php

namespace App\Http\Requests\Monitor;

use App\Services\SsrfGuard;
use Illuminate\Validation\Validator;

class MonitorRules
{
    /** Hardcoded plan limit (PRD §4.2). */
    public const MAX_MONITORS_PER_USER = 10;

    /** PRD §4.2 — allowed check intervals, in seconds. */
    public const ALLOWED_INTERVALS = [60, 300, 900];

    /**
     * Shared validation for create and update.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https', self::blockedUrl()],
            'method' => ['required', 'string', 'in:GET,HEAD'],
            'interval_seconds' => ['required', 'integer', 'in:'.implode(',', self::ALLOWED_INTERVALS)],
            'timeout_seconds' => ['required', 'integer', 'between:5,30'],
            'expected_status' => ['required', 'string', 'max:100', 'regex:/^\d{3}(-\d{3})?(\s*,\s*\d{3}(-\d{3})?)*$/'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'keyword_mode' => ['nullable', 'string', 'in:must_contain,must_not_contain'],
            'follow_redirects' => ['sometimes', 'boolean'],
            'custom_headers' => ['sometimes', 'array', 'max:5'],
            'custom_headers.*' => ['string', 'max:128'],
            'custom_headers.*.*' => ['string', 'max:1024'],
            'alert_after_failures' => ['required', 'integer', 'between:1,5'],
            'is_paused' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Cross-field rules: keyword pairing and the plan limit.
     *
     * @param  callable(): int  $monitorCount  how many monitors the user already owns
     * @return array<string, callable(Validator): void>
     */
    public static function after(callable $monitorCount): array
    {
        return [
            'keyword' => function (Validator $validator) {
                $data = $validator->getData();

                if (! empty($data['keyword']) && empty($data['keyword_mode'])) {
                    $validator->errors()->add('keyword_mode', 'Choose whether the keyword must be present or absent.');
                }
            },
            'url' => function (Validator $validator) use ($monitorCount) {
                if ($monitorCount() >= self::MAX_MONITORS_PER_USER) {
                    $validator->errors()->add(
                        'url',
                        'Plan limit reached: you can monitor up to '.self::MAX_MONITORS_PER_USER.' sites.'
                    );
                }
            },
        ];
    }

    /**
     * PRD §5 — reject structurally unsafe targets at save time.
     */
    private static function blockedUrl(): callable
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            foreach (SsrfGuard::validateUrl((string) $value) as $error) {
                $fail($error);
            }
        };
    }
}
