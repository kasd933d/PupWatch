<?php

namespace Database\Factories;

use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StatusPage>
 */
class StatusPageFactory extends Factory
{
    protected $model = StatusPage::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->company().' status';

        return [
            'user_id' => User::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'description' => null,
            'show_response_times' => false,
            'show_error_details' => false,
            'is_indexable' => true,
            'accent_color' => null,
            'logo_url' => null,
        ];
    }
}
