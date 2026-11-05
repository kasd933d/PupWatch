<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StatusPage\StoreStatusPageRequest;
use App\Http\Requests\StatusPage\UpdateStatusPageRequest;
use App\Http\Resources\StatusPageResource;
use App\Models\StatusPage;
use App\Services\StatusPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class StatusPageController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(StatusPage::class, 'status_page');
    }

    /** GET /status-pages — the signed-in user's pages. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $pages = $request->user()
            ->statusPages()
            ->with('monitors')
            ->orderBy('created_at')
            ->get();

        return StatusPageResource::collection($pages);
    }

    /** POST /status-pages */
    public function store(StoreStatusPageRequest $request): JsonResponse
    {
        $data = $request->validated();
        $monitors = $data['monitors'];
        unset($data['monitors']);

        $page = $request->user()->statusPages()->create([
            ...$data,
            'slug' => $data['slug'] ?? $this->uniqueSlug($data['title']),
        ]);

        $this->syncMonitors($page, $monitors);

        // refresh() so database defaults (is_indexable, ...) are in the response.
        return response()->json(
            ['status_page' => new StatusPageResource($page->refresh()->load('monitors'))],
            201
        );
    }

    /** PATCH /status-pages/{status_page} */
    public function update(UpdateStatusPageRequest $request, StatusPage $statusPage): JsonResponse
    {
        $data = $request->validated();
        $monitors = $data['monitors'] ?? null;
        unset($data['monitors']);

        $statusPage->fill($data)->save();

        if ($monitors !== null) {
            $this->syncMonitors($statusPage, $monitors);
        }

        StatusPageService::forget($statusPage);

        return response()->json([
            'status_page' => new StatusPageResource($statusPage->refresh()->load('monitors')),
        ]);
    }

    /** DELETE /status-pages/{status_page} */
    public function destroy(StatusPage $statusPage): JsonResponse
    {
        StatusPageService::forget($statusPage);
        $statusPage->delete();

        return response()->json(['message' => 'Status page deleted.']);
    }

    /**
     * @param  list<array{id: int, display_name?: string|null}>  $monitors
     */
    private function syncMonitors(StatusPage $page, array $monitors): void
    {
        $sync = [];

        foreach (array_values($monitors) as $index => $monitor) {
            $sync[$monitor['id']] = [
                'display_name' => $monitor['display_name'] ?? null,
                'sort_order' => $index,
            ];
        }

        $page->monitors()->sync($sync);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'status';
        $slug = $base;
        $suffix = 2;

        while (
            StatusPage::where('slug', $slug)->exists()
            || in_array($slug, StatusPage::RESERVED_SLUGS, true)
        ) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
