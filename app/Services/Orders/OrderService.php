<?php

namespace App\Services\Orders;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Services\Crm\LeadService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\Exceptions\OrderException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly LeadService $leads,
    ) {}

    /**
     * Create an order from a Mini App cart. Prices and names are snapshotted
     * from the DB (never trusted from the client — CLAUDE.md rule #1). Stock is
     * decremented immediately via append-only 'out' movements (rule #3). Also
     * resolves/creates the telegram Customer and a CRM Lead.
     *
     * @param  array{items: array<int, array{product_id:int, quantity:int}>, customer_name:string, customer_phone:string, customer_address?:string|null, notes?:string|null}  $data
     * @param  array{id:int|string, first_name?:string, username?:string}  $telegramUser
     */
    public function createFromCart(Store $store, array $data, array $telegramUser): Order
    {
        return DB::transaction(function () use ($store, $data, $telegramUser): Order {
            $requested = $this->normaliseItems($data['items']);

            // Lock the products for the duration of the transaction to avoid two
            // concurrent orders overselling the last unit.
            $products = Product::query()
                ->where('store_id', $store->id)
                ->whereIn('id', array_keys($requested))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lineItems = [];
            $total = 0;

            foreach ($requested as $productId => $qty) {
                $product = $products->get($productId);

                if (! $product || $product->status !== 'active') {
                    throw new OrderException('Mahsulot mavjud emas.');
                }
                if ($product->quantity < $qty) {
                    throw new OrderException("\"{$product->name}\" yetarli emas (omborda {$product->quantity} ta).");
                }

                $subtotal = $product->price * $qty;
                $total += $subtotal;

                $lineItems[] = [
                    'product' => $product,
                    'item' => [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'unit_price' => $product->price,
                        'quantity' => $qty,
                        'subtotal' => $subtotal,
                    ],
                ];
            }

            $customer = Customer::firstOrCreate(
                ['store_id' => $store->id, 'channel' => 'telegram', 'external_id' => (string) $telegramUser['id']],
                ['name' => $data['customer_name'], 'phone' => $data['customer_phone']],
            );

            $order = Order::create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_address' => $data['customer_address'] ?? null,
                'status' => 'new',
                'total' => $total,
                'source' => 'telegram_mini_app',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lineItems as $line) {
                $order->items()->create($line['item']);
                // Append-only stock 'out' movement; status auto-flips at 0.
                $this->inventory->adjustStock($line['product'], 'out', -$line['item']['quantity'], 'Buyurtma');
            }

            $this->leads->createOrUpdate($store, [
                'first_name' => $data['customer_name'],
                'phone' => $data['customer_phone'],
                'customer_id' => $customer->id,
                'source' => 'telegram',
            ]);

            return $order->load('items');
        });
    }

    /**
     * Merge duplicate product_ids and validate quantities.
     *
     * @param  array<int, array{product_id:int, quantity:int}>  $items
     * @return array<int, int> product_id => quantity
     */
    private function normaliseItems(array $items): array
    {
        $merged = [];

        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $qty = (int) $item['quantity'];

            if ($id <= 0 || $qty <= 0) {
                throw new OrderException('Savatcha noto\'g\'ri.');
            }

            $merged[$id] = ($merged[$id] ?? 0) + $qty;
        }

        if ($merged === []) {
            throw new OrderException('Savatcha bo\'sh.');
        }

        return $merged;
    }
}
