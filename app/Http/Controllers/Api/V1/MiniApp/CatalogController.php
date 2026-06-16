<?php

namespace App\Http\Controllers\Api\V1\MiniApp;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public catalog for the Telegram Mini App. Tenancy is set by the
 * store.public middleware, so the BelongsToStore scope already constrains
 * every query to the resolved store.
 */
class CatalogController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('images')
            ->where('status', 'active')
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->orderByDesc('quantity')
            ->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    public function show(Request $request, string $store_public_id, string $product): JsonResponse
    {
        // Resolve explicitly within the active tenant scope rather than relying
        // on implicit route-model binding for this public surface.
        $model = Product::query()
            ->where('status', 'active')
            ->with('images')
            ->find((int) $product);

        if (! $model) {
            abort(404, 'Product not found.');
        }

        return $this->ok(new ProductResource($model));
    }
}
