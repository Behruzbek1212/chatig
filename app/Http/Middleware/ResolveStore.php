<?php

namespace App\Http\Middleware;

use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the authenticated user's store once per request and binds it to
 * the Tenancy singleton, which the BelongsToStore trait uses to scope queries.
 */
class ResolveStore
{
    public function __construct(private readonly Tenancy $tenancy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->store_id !== null) {
            $this->tenancy->set($user->store);
        }

        return $next($request);
    }
}
