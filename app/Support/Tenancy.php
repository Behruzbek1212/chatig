<?php

namespace App\Support;

use App\Models\Store;

/**
 * Holds the current tenant (Store) for the request lifecycle.
 *
 * Resolved once by the ResolveStore middleware from the authenticated user.
 * The BelongsToStore trait reads from here to scope queries — store_id is
 * never accepted from client input.
 */
class Tenancy
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function get(): ?Store
    {
        return $this->store;
    }

    public function id(): ?int
    {
        return $this->store?->id;
    }

    public function check(): bool
    {
        return $this->store !== null;
    }

    public function forget(): void
    {
        $this->store = null;
    }
}
