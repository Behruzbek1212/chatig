<?php

namespace App\Http\Controllers\Api\V1\MiniApp;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\MiniApp\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Store;
use App\Services\Orders\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends ApiController
{
    public function __construct(private readonly OrderService $orders) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var Store $store */
        $store = $request->attributes->get('current_store');
        /** @var array<string, mixed> $tgUser */
        $tgUser = $request->attributes->get('tg_user');

        // OrderException (insufficient stock, invalid cart, ...) self-renders as 422.
        $order = $this->orders->createFromCart($store, $request->validated(), $tgUser);

        return $this->ok(new OrderResource($order), status: 201);
    }
}
