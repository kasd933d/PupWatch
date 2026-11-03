<?php

namespace Database\Factories;

use App\Models\NotificationChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    protected $model = NotificationChannel::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => NotificationChannel::TYPE_DISCORD,
            'name' => 'Ops Discord',
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/123456789/abcdef'],
            'status' => NotificationChannel::STATUS_ACTIVE,
            'last_used_at' => null,
        ];
    }

    public function telegram(string $chatId = '424242'): static
    {
        return $this->state(fn () => [
            'type' => NotificationChannel::TYPE_TELEGRAM,
            'name' => '@tester',
            'config' => ['chat_id' => $chatId, 'username' => 'tester'],
        ]);
    }

    public function needsAttention(): static
    {
        return $this->state(fn () => ['status' => NotificationChannel::STATUS_NEEDS_ATTENTION]);
    }
}
