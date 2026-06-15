<?php

namespace App\Agents\Tools;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Store;

/**
 * Carries the tenant + conversation context a tool needs to act. Passed to
 * every Tool::handle() call so tools never read store_id from model input.
 */
class ToolContext
{
    public function __construct(
        public readonly Store $store,
        public readonly ?Conversation $conversation = null,
        public readonly ?Customer $customer = null,
    ) {}
}
