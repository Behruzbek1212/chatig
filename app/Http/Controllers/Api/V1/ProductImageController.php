<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Inventory\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductImageController extends ApiController
{
    public function __construct(private readonly ProductImageService $images) {}

    public function store(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'max:'.ProductImageService::MAX_IMAGES],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $existing = $product->images()->count();
        if ($existing + count($validated['images']) > ProductImageService::MAX_IMAGES) {
            return response()->json(['message' => 'Eng ko\'pi bilan 10 ta rasm bo\'lishi mumkin.'], 422);
        }

        $this->images->addImages($product, $request->file('images'));

        return $this->ok(new ProductResource($product->load('images')));
    }

    public function destroy(Product $product, ProductImage $image): JsonResponse
    {
        $this->guardOwnership($product, $image);
        $this->images->deleteImage($image);

        return $this->ok(new ProductResource($product->load('images')));
    }

    public function setPrimary(Product $product, ProductImage $image): JsonResponse
    {
        $this->guardOwnership($product, $image);
        $this->images->setPrimary($product, $image);

        return $this->ok(new ProductResource($product->load('images')));
    }

    public function reorder(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $this->images->reorder($product, $validated['order']);

        return $this->ok(new ProductResource($product->load('images')));
    }

    private function guardOwnership(Product $product, ProductImage $image): void
    {
        if ($image->product_id !== $product->id) {
            throw new NotFoundHttpException;
        }
    }
}
