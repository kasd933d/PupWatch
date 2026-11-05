<?php

namespace App\Models;

use Database\Factories\StatusPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A public, shareable page for a selection of monitors (PRD §4.8).
 */
class StatusPage extends Model
{
    /** @use HasFactory<StatusPageFactory> */
    use HasFactory;

    /** Slugs that would collide with application routes or look like system paths. */
    public const RESERVED_SLUGS = [
        'api', 'admin', 'dashboard', 'login', 'register', 'logout', 'status',
        'monitors', 'incidents', 'channels', 'settings', 'status-pages', 'new',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'show_response_times',
        'show_error_details',
        'is_indexable',
        'accent_color',
        'logo_url',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'show_response_times' => 'boolean',
            'show_error_details' => 'boolean',
            'is_indexable' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Monitor, $this> */
    public function monitors(): BelongsToMany
    {
        return $this->belongsToMany(Monitor::class, 'status_page_monitor')
            ->withPivot(['display_name', 'sort_order'])
            ->orderBy('status_page_monitor.sort_order')
            ->orderBy('status_page_monitor.id');
    }
}
