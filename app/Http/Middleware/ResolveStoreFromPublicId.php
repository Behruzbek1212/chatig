<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the tenant for public Mini App requests from the {store_public_id}
 * route segment and binds it via the Tenancy singleton, so BelongsToStore
 * scoping works without an authenticated user (mirrors how the inbound
 * Instagram job sets tenancy). Store itself has no store scope, so resolving
 * it here is safe; tenancy MUST be set before any controller touches a
 * store-scoped model. Must run AFTER VerifyTelegramInitData.
 */
class ResolveStoreFromPublicId
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $publicId = (string) $request->route('store_public_id');

        $store = Store::query()->where('public_id', $publicId)->first();

        if (! $store || $store->status !== 'active') {
            abort(404, 'Store not found.');
        }

        // Defense in depth: a customer who authenticated via store A's deep link
        // must not query store B by swapping the path segment.
        $startParam = (string) $request->attributes->get('tg_start_param', '');
        if ($startParam !== '' && $startParam !== $publicId) {
            abort(403, 'Store mismatch.');
        }

        $this->tenancy->set($store);
        $request->attributes->set('current_store', $store);

        return $next($request);
    }
}
