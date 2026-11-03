<?php

namespace App\Http\Requests\Channel;

use App\Services\Alerts\DiscordChannel;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscordChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'webhook_url' => ['required', 'string', 'max:500', 'regex:'.DiscordChannel::WEBHOOK_PATTERN],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'webhook_url.regex' => 'That does not look like a Discord webhook URL (https://discord.com/api/webhooks/…).',
        ];
    }
}
