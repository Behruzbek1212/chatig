<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ChannelResource;
use App\Models\Channel;
use App\Services\Channels\Exceptions\InstagramException;
use App\Services\Channels\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class IntegrationController extends ApiController
{
    public function __construct(private readonly InstagramService $instagram) {}

    public function index(): AnonymousResourceCollection
    {
        return ChannelResource::collection(Channel::query()->latest()->get());
    }

    public function connectUrl(Request $request): JsonResponse
    {
        return $this->ok([
            'url' => $this->instagram->connectUrl($request->user()->store),
        ]);
    }

    /**
     * Public endpoint hit by Instagram's browser redirect. Resolves the store
     * from the signed state, then redirects back to the SPA with status.
     */
    public function callback(Request $request): RedirectResponse
    {
        $spa = rtrim(config('chatig.spa_url'), '/').'/integrations';

        if ($request->filled('error') || ! $request->filled('code') || ! $request->filled('state')) {
            return redirect()->away($spa.'?instagram=error');
        }

        try {
            $this->instagram->handleCallback($request->string('code')->value(), $request->string('state')->value());
        } catch (InstagramException) {
            return redirect()->away($spa.'?instagram=error');
        }

        return redirect()->away($spa.'?instagram=connected');
    }

    public function destroy(Channel $channel): JsonResponse
    {
        $this->instagram->disconnect($channel);

        return $this->ok(new ChannelResource($channel->refresh()));
    }
}
