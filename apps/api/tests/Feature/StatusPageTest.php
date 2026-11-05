<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: string} */
    private function actingUser(): array
    {
        $user = User::factory()->create();

        return [$user, $user->createToken('web')->plainTextToken];
    }

    public function test_a_status_page_is_created_with_selected_monitors(): void
    {
        [$user, $token] = $this->actingUser();
        $api = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'API']);
        $site = Monitor::factory()->create(['user_id' => $user->id, 'name' => 'Website']);

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Acme Status',
            'description' => 'Live status for Acme clients',
            'monitors' => [
                ['id' => $api->id, 'display_name' => 'Acme API'],
                ['id' => $site->id],
            ],
        ])->assertCreated()
            ->assertJsonPath('status_page.slug', 'acme-status')
            ->assertJsonPath('status_page.public_path', '/status/acme-status')
            ->assertJsonPath('status_page.monitors.0.name', 'Acme API')
            ->assertJsonPath('status_page.monitors.1.name', 'Website')
            ->assertJsonPath('status_page.show_response_times', false);

        $this->assertDatabaseHas('status_page_monitor', [
            'monitor_id' => $api->id,
            'display_name' => 'Acme API',
            'sort_order' => 0,
        ]);
    }

    public function test_a_slug_can_be_supplied_but_reserved_words_are_rejected(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Anything',
            'slug' => 'status',
            'monitors' => [['id' => $monitor->id]],
        ])->assertStatus(422)->assertJsonValidationErrors(['slug']);

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Anything',
            'slug' => 'Acme-Status',
            'monitors' => [['id' => $monitor->id]],
        ])->assertCreated()
            ->assertJsonPath('status_page.slug', 'acme-status');
    }

    public function test_slugs_are_unique_and_generated_slugs_get_a_suffix(): void
    {
        [$user, $token] = $this->actingUser();
        $monitor = Monitor::factory()->create(['user_id' => $user->id]);
        StatusPage::factory()->create(['slug' => 'acme-status']);

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Acme status',
            'monitors' => [['id' => $monitor->id]],
        ])->assertCreated()
            ->assertJsonPath('status_page.slug', 'acme-status-2');
    }

    public function test_monitors_must_belong_to_the_user(): void
    {
        [, $token] = $this->actingUser();
        $foreign = Monitor::factory()->create();

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Sneaky',
            'monitors' => [['id' => $foreign->id]],
        ])->assertStatus(422)->assertJsonValidationErrors(['monitors.0.id']);
    }

    public function test_a_status_page_needs_at_least_one_monitor(): void
    {
        [, $token] = $this->actingUser();

        $this->withToken($token)->postJson('/api/v1/status-pages', [
            'title' => 'Empty',
            'monitors' => [],
        ])->assertStatus(422)->assertJsonValidationErrors(['monitors']);
    }

    public function test_users_only_see_and_change_their_own_pages(): void
    {
        [$user, $token] = $this->actingUser();
        $own = StatusPage::factory()->create(['user_id' => $user->id]);
        $foreign = StatusPage::factory()->create();

        $this->withToken($token)->getJson('/api/v1/status-pages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->withToken($token)->patchJson('/api/v1/status-pages/'.$foreign->id, ['title' => 'Hijacked'])
            ->assertForbidden();

        $this->withToken($token)->deleteJson('/api/v1/status-pages/'.$foreign->id)
            ->assertForbidden();

        $this->assertDatabaseHas('status_pages', ['id' => $foreign->id]);
    }

    public function test_updating_replaces_monitors_and_privacy_flags(): void
    {
        [$user, $token] = $this->actingUser();
        $page = StatusPage::factory()->create(['user_id' => $user->id]);
        $first = Monitor::factory()->create(['user_id' => $user->id]);
        $second = Monitor::factory()->create(['user_id' => $user->id]);
        $page->monitors()->attach($first->id, ['sort_order' => 0]);

        $this->withToken($token)->patchJson('/api/v1/status-pages/'.$page->id, [
            'title' => 'Acme Public Status',
            'show_response_times' => true,
            'show_error_details' => true,
            'is_indexable' => false,
            'accent_color' => '#2563eb',
            'monitors' => [['id' => $second->id, 'display_name' => 'Client portal']],
        ])->assertOk()
            ->assertJsonPath('status_page.title', 'Acme Public Status')
            ->assertJsonPath('status_page.show_response_times', true)
            ->assertJsonPath('status_page.is_indexable', false)
            ->assertJsonPath('status_page.monitors.0.id', $second->id);

        $this->assertDatabaseMissing('status_page_monitor', [
            'status_page_id' => $page->id,
            'monitor_id' => $first->id,
        ]);
    }

    public function test_a_page_can_be_deleted(): void
    {
        [$user, $token] = $this->actingUser();
        $page = StatusPage::factory()->create(['user_id' => $user->id]);

        $this->withToken($token)->deleteJson('/api/v1/status-pages/'.$page->id)->assertOk();

        $this->assertDatabaseMissing('status_pages', ['id' => $page->id]);
    }
}
