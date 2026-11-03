<?php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    protected $model = Monitor::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $url = 'https://'.$this->faker->domainName().'/health';

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'url' => $url,
            'host' => parse_url($url, PHP_URL_HOST),
            'method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 10,
            'expected_status' => '200-399',
            'keyword' => null,
            'keyword_mode' => null,
            'follow_redirects' => true,
            'custom_headers' => null,
            'alert_after_failures' => 2,
            'is_paused' => false,
            'current_status' => Monitor::STATUS_PENDING,
            'consecutive_failures' => 0,
            'next_check_at' => now(),
        ];
    }
}
