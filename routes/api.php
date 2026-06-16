<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\MiniApp\CatalogController;
use App\Http\Controllers\Api\V1\MiniApp\OrderController as MiniAppOrderController;
use App\Http\Controllers\Api\V1\MiniApp\StoreController as MiniAppStoreController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\Settings\AiSettingsController;
use App\Http\Controllers\Api\V1\ShopFactController;
use App\Http\Controllers\Api\V1\Webhooks\InstagramWebhookController;
use App\Http\Middleware\ResolveStoreFromPublicId;
use App\Http\Middleware\VerifyInstagramSignature;
use App\Http\Middleware\VerifyTelegramInitData;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Instagram webhook (public; POST is signature-verified) ---
    Route::get('webhooks/instagram', [InstagramWebhookController::class, 'verify']);
    Route::post('webhooks/instagram', [InstagramWebhookController::class, 'handle'])
        ->middleware(VerifyInstagramSignature::class);

    // --- Auth (public) ---
    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('register/verify', [AuthController::class, 'verifyRegister']);
        Route::post('login', [AuthController::class, 'login']);
        Route::post('login/verify', [AuthController::class, 'verifyLogin']);
        Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    });

    // Instagram OAuth callback is hit by Meta's browser redirect (no auth header).
    Route::get('integrations/instagram/callback', [IntegrationController::class, 'callback']);

    // --- Telegram Mini App (public; customer auth via verified initData) ---
    Route::prefix('mini-app')
        ->middleware(VerifyTelegramInitData::class)
        ->group(function () {
            Route::prefix('stores/{store_public_id}')
                ->middleware(ResolveStoreFromPublicId::class)
                ->group(function () {
                    Route::get('store', [MiniAppStoreController::class, 'show']);
                    Route::get('products', [CatalogController::class, 'index']);
                    Route::get('products/{product}', [CatalogController::class, 'show']);
                    Route::post('orders', [MiniAppOrderController::class, 'store']);
                });
        });

    // --- Authenticated + store-scoped ---
    Route::middleware(['auth:sanctum', 'store'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);

        Route::get('dashboard/summary', [DashboardController::class, 'summary']);

        // Integrations
        Route::get('integrations', [IntegrationController::class, 'index']);
        Route::get('integrations/instagram/connect-url', [IntegrationController::class, 'connectUrl']);
        Route::get('integrations/instagram/auth', [IntegrationController::class, 'auth']);
        Route::post('integrations/telegram/connect', [IntegrationController::class, 'telegramConnect']);
        Route::delete('integrations/{channel}', [IntegrationController::class, 'destroy']);

        // Inventory
        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::patch('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::get('products/{product}/movements', [ProductController::class, 'movements']);

        // Product images
        Route::post('products/{product}/images', [ProductImageController::class, 'store']);
        Route::delete('products/{product}/images/{image}', [ProductImageController::class, 'destroy']);
        Route::patch('products/{product}/images/reorder', [ProductImageController::class, 'reorder']);
        Route::patch('products/{product}/images/{image}/primary', [ProductImageController::class, 'setPrimary']);

        // CRM / Leads
        Route::get('leads', [LeadController::class, 'index']);
        Route::get('leads/{lead}', [LeadController::class, 'show']);
        Route::patch('leads/{lead}', [LeadController::class, 'update']);

        // Shop facts (address, phone, working hours, delivery/return policy, ...)
        Route::get('shop-facts', [ShopFactController::class, 'index']);
        Route::post('shop-facts', [ShopFactController::class, 'store']);
        Route::get('shop-facts/{shopFact}', [ShopFactController::class, 'show']);
        Route::patch('shop-facts/{shopFact}', [ShopFactController::class, 'update']);
        Route::delete('shop-facts/{shopFact}', [ShopFactController::class, 'destroy']);

        // AI settings
        Route::post('ai/prompt/generate', [AiSettingsController::class, 'generate']);
        Route::post('ai/prompt/test', [AiSettingsController::class, 'test']);
        Route::get('ai/settings', [AiSettingsController::class, 'show']);
        Route::put('ai/settings', [AiSettingsController::class, 'update']);
    });
});
