<?php

namespace App\Services\Inventory;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Create a product. Initial quantity is applied via a stock movement,
     * never written directly (CLAUDE.md rule #3).
     *
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $initialQty = (int) ($data['quantity'] ?? 0);

            $product = Product::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'quantity' => 0,
                'category' => $data['category'] ?? null,
                'condition' => $data['condition'] ?? null,
                'brand' => $data['brand'] ?? null,
                'status' => 'active',
            ]);

            if ($initialQty !== 0) {
                $this->recordMovement($product, 'initial', $initialQty, 'Boshlang\'ich qoldiq');
            } else {
                $this->refreshStatus($product);
                $product->save();
            }

            return $product->refresh();
        });
    }

    /**
     * Update editable product fields. Quantity is intentionally NOT updatable
     * here — use adjustStock().
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        $product->fill(collect($data)->only([
            'name', 'description', 'price', 'category', 'condition', 'brand',
        ])->all());
        $product->save();

        return $product->refresh();
    }

    /**
     * Apply a stock change by appending a movement and recomputing quantity.
     */
    public function adjustStock(Product $product, string $type, int $qtyChange, ?string $reason = null): Product
    {
        return DB::transaction(function () use ($product, $type, $qtyChange, $reason): Product {
            $this->recordMovement($product, $type, $qtyChange, $reason);

            return $product->refresh();
        });
    }

    /**
     * Structured search used by the AI sales tool and the products list.
     *
     * @return Collection<int, Product>
     */
    public function search(Store $store, string $query): Collection
    {
        return Product::query()
            ->where('store_id', $store->id)
            ->where(function ($q) use ($query): void {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%");
            })
            ->orderByDesc('quantity')
            ->limit(20)
            ->get();
    }

    private function recordMovement(Product $product, string $type, int $qtyChange, ?string $reason): void
    {
        $product->movements()->create([
            'type' => $type,
            'qty_change' => $qtyChange,
            'reason' => $reason,
        ]);

        $product->quantity += $qtyChange;
        $this->refreshStatus($product);
        $product->save();
    }

    private function refreshStatus(Product $product): void
    {
        $product->status = $product->quantity <= 0 ? 'out_of_stock' : 'active';
    }
}
