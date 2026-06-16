<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageService
{
    public const MAX_IMAGES = 10;

    /**
     * Store uploaded images for a product, preserving order. The first image
     * overall becomes primary if none exists yet.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function addImages(Product $product, array $files): void
    {
        DB::transaction(function () use ($product, $files): void {
            $position = (int) $product->images()->max('position');
            $hasPrimary = $product->images()->where('is_primary', true)->exists();

            $disk = config('chatig.media_disk');

            foreach ($files as $file) {
                $path = $file->store("products/{$product->id}", $disk);

                /** @var ProductImage $image */
                $image = $product->images()->create([
                    'path' => $path,
                    'position' => ++$position,
                    'is_primary' => false,
                ]);

                if (! $hasPrimary) {
                    $this->setPrimary($product, $image);
                    $hasPrimary = true;
                }
            }
        });
    }

    public function deleteImage(ProductImage $image): void
    {
        Storage::disk(config('chatig.media_disk'))->delete($image->path);
        $wasPrimary = $image->is_primary;
        $product = $image->product;
        $image->delete();

        if ($wasPrimary) {
            /** @var ProductImage|null $next */
            $next = $product->images()->orderBy('position')->first();
            if ($next) {
                $this->setPrimary($product, $next);
            }
        }
    }

    public function setPrimary(Product $product, ProductImage $image): void
    {
        $product->images()->update(['is_primary' => false]);
        $image->update(['is_primary' => true]);
    }

    /**
     * Reorder images by an ordered list of image ids.
     *
     * @param  array<int, int>  $orderedIds
     */
    public function reorder(Product $product, array $orderedIds): void
    {
        DB::transaction(function () use ($product, $orderedIds): void {
            $position = 0;
            foreach ($orderedIds as $id) {
                $product->images()->where('id', $id)->update(['position' => ++$position]);
            }
        });
    }
}
