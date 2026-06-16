<?php

namespace App\Http\Controllers\Api\V1\MiniApp;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ShopFactResource;
use App\Models\Store;
use App\Services\ShopFacts\ShopFactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public store header for the Mini App: shop name + facts (address, phone,
 * working hours, delivery/return policy, ...).
 */
class StoreController extends ApiController
{
    public function show(Request $request, ShopFactService $shopFacts): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('current_store');

        return $this->ok([
            'name' => $store->name,
            'facts' => ShopFactResource::collection($shopFacts->list($store)),
        ]);
    }
}
