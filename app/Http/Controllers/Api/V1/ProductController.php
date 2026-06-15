<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends ApiController
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProductImageService $images,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('images')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy($this->sortColumn($request), $this->sortDirection($request))
            ->paginate($request->integer('per_page', 20));

        return ProductResource::collection($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->inventory->createProduct($request->validated());

        if ($request->hasFile('images')) {
            $this->images->addImages($product, $request->file('images'));
        }

        return $this->ok(new ProductResource($product->load('images')), status: 201);
    }

    public function show(Product $product): JsonResponse
    {
        return $this->ok(new ProductResource($product->load('images')));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product = $this->inventory->updateProduct($product, $request->validated());

        return $this->ok(new ProductResource($product->load('images')));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return $this->message('Tovar o\'chirildi.');
    }

    public function movements(Product $product): AnonymousResourceCollection
    {
        return StockMovementResource::collection($product->movements()->paginate(30));
    }

    private function sortColumn(Request $request): string
    {
        return in_array($request->string('sort')->value(), ['name', 'price', 'quantity', 'created_at'], true)
            ? $request->string('sort')->value()
            : 'created_at';
    }

    private function sortDirection(Request $request): string
    {
        return $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';
    }
}
