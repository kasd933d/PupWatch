<?php

namespace App\Http\Requests\Channel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkChannelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('monitor')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'channel_ids' => ['sometimes', 'array', 'max:20'],
            'channel_ids.*' => [
                'integer',
                // PRD §5 — never trust a client-supplied id to imply ownership.
                Rule::exists('notification_channels', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
