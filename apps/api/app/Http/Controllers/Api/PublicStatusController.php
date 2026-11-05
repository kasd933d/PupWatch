<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StatusPage;
use App\Services\StatusPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class PublicStatusController extends Controller
{
    /**
     * GET /public/status/{slug} — cached for 60 seconds (PRD §4.8).
     *
     * The page is looked up by slug with implicit 404 binding, so a wrong slug
     * cannot reveal whether a private page exists under another id.
     */
    public function show(StatusPage $statusPage, StatusPageService $service): JsonResponse
    {
        $ttl = (int) config('pupwatch.status_page_cache_seconds', 60);

        $payload = Cache::remember(
            StatusPageService::cacheKey($statusPage->slug),
            $ttl,
            fn () => $service->payload($statusPage)
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age='.$ttl);
    }
}
