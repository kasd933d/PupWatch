<?php

namespace Database\Seeders;

use App\Models\CheckResult;
use App\Models\DailyStat;
use App\Models\Incident;
use App\Models\Monitor;
use App\Models\NotificationChannel;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Deterministic demo data for screenshots, local development and the live
 * demo status page (PRD §15, Milestone 6).
 *
 * Re-running it replaces the demo account, so it stays safe to call.
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@pupwatch.dev';

    public const PASSWORD = 'pupwatch-demo';

    public function run(): void
    {
        if (app()->environment('production') && ! env('DEMO_SEED', false)) {
            $this->command?->warn('Skipping DemoSeeder in production (set DEMO_SEED=true to force).');

            return;
        }

        User::where('email', self::EMAIL)->delete();

        $user = User::create([
            'name' => 'Demo Owner',
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
            'timezone' => 'UTC',
        ]);

        $homepage = $this->monitor($user, 'Acme homepage', 'https://acme.test/', [
            'current_status' => Monitor::STATUS_UP,
            'last_response_ms' => 184,
        ]);
        $portal = $this->monitor($user, 'Client portal', 'https://portal.acme.test/', [
            'current_status' => Monitor::STATUS_UP,
            'last_response_ms' => 1480,
        ]);
        $docs = $this->monitor($user, 'Docs site', 'https://docs.acme.test/', [
            'current_status' => Monitor::STATUS_DOWN,
            'last_response_ms' => null,
            'consecutive_failures' => 3,
        ]);

        $this->historyFor($homepage, 'excellent');
        $this->historyFor($portal, 'slow');
        $this->historyFor($docs, 'flaky');

        $docs->forceFill([
            'last_checked_at' => now()->subMinutes(2),
            'next_check_at' => now()->addMinutes(3),
        ])->save();

        Incident::create([
            'monitor_id' => $docs->id,
            'started_at' => now()->subMinutes(20),
            'ended_at' => null,
            'duration_seconds' => null,
            'first_error_type' => 'timeout',
            'first_error_message' => 'Timed out after 10s',
            'status' => Incident::STATUS_OPEN,
        ]);

        Incident::create([
            'monitor_id' => $portal->id,
            'started_at' => now()->subDays(4)->setTime(9, 12),
            'ended_at' => now()->subDays(4)->setTime(9, 31),
            'duration_seconds' => 19 * 60,
            'first_error_type' => 'http_error',
            'first_error_message' => 'Expected 200-299, received 502',
            'status' => Incident::STATUS_RESOLVED,
        ]);

        $channel = NotificationChannel::create([
            'user_id' => $user->id,
            'type' => NotificationChannel::TYPE_DISCORD,
            'name' => 'Acme ops',
            'config' => ['webhook_url' => 'https://discord.com/api/webhooks/000000000000000000/demo-token'],
            'status' => NotificationChannel::STATUS_ACTIVE,
            'last_used_at' => now()->subDays(4),
        ]);
        $channel->monitors()->sync([$homepage->id, $portal->id, $docs->id]);

        $page = StatusPage::create([
            'user_id' => $user->id,
            'title' => 'Acme Status',
            'slug' => 'pupwatch-demo',
            'description' => 'Live status for Acme client sites.',
            'show_response_times' => true,
            'show_error_details' => false,
            'is_indexable' => true,
            'accent_color' => '#2563eb',
        ]);

        $page->monitors()->sync([
            $homepage->id => ['display_name' => 'acme.com', 'sort_order' => 0],
            $portal->id => ['display_name' => 'Client portal', 'sort_order' => 1],
            $docs->id => ['display_name' => 'Documentation', 'sort_order' => 2],
        ]);

        $this->command?->info('Demo account: '.self::EMAIL.' / '.self::PASSWORD);
        $this->command?->info('Demo status page: /status/pupwatch-demo');
    }

    /** @param array<string, mixed> $attributes */
    private function monitor(User $user, string $name, string $url, array $attributes): Monitor
    {
        return Monitor::create([
            'user_id' => $user->id,
            'name' => $name,
            'url' => $url,
            'host' => parse_url($url, PHP_URL_HOST),
            'method' => 'GET',
            'interval_seconds' => 300,
            'timeout_seconds' => 10,
            'expected_status' => '200-299',
            'follow_redirects' => true,
            'alert_after_failures' => 2,
            'is_paused' => false,
            'consecutive_failures' => 0,
            'last_checked_at' => now()->subMinutes(3),
            'next_check_at' => now()->addMinutes(2),
            ...$attributes,
        ]);
    }

    /**
     * 90 days of rollups plus today's raw checks.
     */
    private function historyFor(Monitor $monitor, string $profile): void
    {
        $start = now()->subDays(89)->startOfDay();

        for ($offset = 0; $offset < 89; $offset++) {
            $date = $start->copy()->addDays($offset);
            $uptime = $this->uptimeFor($profile, $offset);

            if ($uptime === null) {
                continue;
            }

            $total = 288;
            $successful = (int) round($total * $uptime / 100);
            $base = match ($profile) {
                'slow' => 1200,
                'flaky' => 380,
                default => 150,
            };

            DailyStat::create([
                'monitor_id' => $monitor->id,
                'date' => $date->toDateString(),
                'total_checks' => $total,
                'successful_checks' => $successful,
                'avg_response_ms' => $base,
                'min_response_ms' => (int) round($base * 0.6),
                'max_response_ms' => (int) round($base * ($profile === 'flaky' ? 6 : 2.4)),
                'p95_response_ms' => (int) round($base * 1.8),
                'downtime_seconds' => (int) round(($total - $successful) * 5),
            ]);
        }

        // Today, live checks (also what the charts read).
        $checks = $profile === 'flaky' ? 8 : 12;

        for ($i = $checks; $i >= 1; $i--) {
            $failed = $profile === 'flaky' && $i <= 3;

            CheckResult::create([
                'monitor_id' => $monitor->id,
                'checked_at' => now()->subMinutes($i * 5),
                'status_code' => $failed ? null : 200,
                'response_time_ms' => $failed ? null : match ($profile) {
                    'slow' => random_int(1100, 1900),
                    'flaky' => random_int(250, 700),
                    default => random_int(120, 260),
                },
                'is_success' => ! $failed,
                'error_type' => $failed ? 'timeout' : null,
                'error_message' => $failed ? 'Timed out after 10s' : null,
            ]);
        }
    }

    /**
     * A believable uptime curve: occasional dips for "flaky", almost perfect
     * for the others, with one or two no-data days.
     */
    private function uptimeFor(string $profile, int $offset): ?float
    {
        if ($offset % 23 === 7) {
            return null;
        }

        return match ($profile) {
            'flaky' => match (true) {
                $offset % 11 === 3 => 92.4,
                $offset % 7 === 2 => 97.8,
                $offset % 17 === 5 => 99.2,
                default => 100.0,
            },
            'slow' => $offset % 9 === 4 ? 99.65 : 100.0,
            default => $offset % 13 === 6 ? 99.93 : 100.0,
        };
    }
}
