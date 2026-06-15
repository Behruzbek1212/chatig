<?php

namespace App\Services\Dashboard;

use App\Models\AiConfig;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Store;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Store $store): array
    {
        $instagramConnected = Channel::where('store_id', $store->id)
            ->where('type', 'instagram')->where('status', 'connected')->exists();

        $hasProducts = Product::where('store_id', $store->id)->exists();

        $aiConfigured = AiConfig::where('store_id', $store->id)->where('is_active', true)->exists();

        return [
            'today' => [
                'conversations' => Conversation::where('store_id', $store->id)->whereDate('created_at', today())->count(),
                'new_leads' => Lead::where('store_id', $store->id)->whereDate('created_at', today())->count(),
                'products_total' => Product::where('store_id', $store->id)->count(),
            ],
            'checklist' => [
                'instagram_connected' => $instagramConnected,
                'has_products' => $hasProducts,
                'ai_configured' => $aiConfigured,
            ],
            'subscription' => [
                'trial_ends_at' => $store->trial_ends_at?->toIso8601String(),
                'days_left' => $store->trial_ends_at ? max(0, (int) now()->diffInDays($store->trial_ends_at, false)) : 0,
                'on_trial' => $store->isOnTrial(),
            ],
        ];
    }
}
