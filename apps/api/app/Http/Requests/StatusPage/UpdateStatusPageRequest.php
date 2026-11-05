<?php

namespace App\Http\Requests\StatusPage;

use App\Models\StatusPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStatusPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var StatusPage $page */
        $page = $this->route('status_page');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('status_pages', 'slug')->ignore($page->id),
            ],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'show_response_times' => ['sometimes', 'boolean'],
            'show_error_details' => ['sometimes', 'boolean'],
            'is_indexable' => ['sometimes', 'boolean'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'monitors' => ['sometimes', 'array', 'min:1', 'max:20'],
            'monitors.*.id' => [
                'required', 'integer', 'distinct',
                Rule::exists('monitors', 'id')->where('user_id', $this->user()->id),
            ],
            'monitors.*.display_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('slug') && $this->input('slug') !== null) {
            $this->merge(['slug' => strtolower(trim((string) $this->input('slug')))]);
        }
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $slug = $this->input('slug');

                if (is_string($slug) && in_array($slug, StatusPage::RESERVED_SLUGS, true)) {
                    $validator->errors()->add('slug', 'That slug is reserved. Please pick another one.');
                }
            },
        ];
    }
}
