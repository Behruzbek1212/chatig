<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\ShopFact\StoreShopFactRequest;
use App\Http\Requests\ShopFact\UpdateShopFactRequest;
use App\Http\Resources\ShopFactResource;
use App\Models\ShopFact;
use App\Services\ShopFacts\ShopFactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ShopFactController extends ApiController
{
    public function __construct(private readonly ShopFactService $shopFacts) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return ShopFactResource::collection($this->shopFacts->list($request->user()->store));
    }

    public function store(StoreShopFactRequest $request): JsonResponse
    {
        $fact = $this->shopFacts->create($request->user()->store, $request->validated());

        return $this->ok(new ShopFactResource($fact), status: 201);
    }

    public function show(ShopFact $shopFact): JsonResponse
    {
        return $this->ok(new ShopFactResource($shopFact));
    }

    public function update(UpdateShopFactRequest $request, ShopFact $shopFact): JsonResponse
    {
        $fact = $this->shopFacts->update($shopFact, $request->validated());

        return $this->ok(new ShopFactResource($fact));
    }

    public function destroy(ShopFact $shopFact): JsonResponse
    {
        $this->shopFacts->delete($shopFact);

        return $this->message('Ma\'lumot o\'chirildi.');
    }
}
