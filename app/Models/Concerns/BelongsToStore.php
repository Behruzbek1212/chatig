<?php

namespace App\Models\Concerns;

use App\Models\Store;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Multi-tenancy: scopes every query to the current store and auto-fills
 * store_id on create. The current store comes from the Tenancy singleton
 * (set by ResolveStore middleware), falling back to the authenticated user's
 * store — so scoping holds even if route-model binding resolves before the
 * middleware. store_id is never accepted from client input.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $builder): void {
            $storeId = static::currentStoreId();
            if ($storeId !== null) {
                $builder->where($builder->getModel()->getTable().'.store_id', $storeId);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->store_id)) {
                $model->store_id = static::currentStoreId();
            }
        });
    }

    protected static function currentStoreId(): ?int
    {
        $tenancy = app(Tenancy::class);

        if ($tenancy->check()) {
            return $tenancy->id();
        }

        return Auth::user()?->store_id;
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
