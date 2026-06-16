<?php

namespace App\Providers;

use App\Services\Llm\Contracts\EmbeddingClient;
use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\FakeEmbeddingClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\OpenAiClient;
use App\Services\Llm\OpenAiEmbeddingClient;
use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\EskizSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Tenancy::class);

        $this->app->bind(SmsSender::class, function ($app): SmsSender {
            return match (config('chatig.sms.driver')) {
                'eskiz' => new EskizSmsSender(config('chatig.sms.eskiz')),
                default => new LogSmsSender,
            };
        });

        $this->app->bind(LlmClient::class, function ($app): LlmClient {
            return match (config('chatig.llm.driver')) {
                'fake' => new FakeLlmClient,
                default => new OpenAiClient(config('chatig.llm.api_key')),
            };
        });

        $this->app->bind(EmbeddingClient::class, function ($app): EmbeddingClient {
            $dimensions = (int) config('chatig.llm.embedding.dimensions');

            return match (config('chatig.llm.driver')) {
                'fake' => new FakeEmbeddingClient($dimensions),
                default => new OpenAiEmbeddingClient(
                    config('chatig.llm.api_key'),
                    config('chatig.llm.embedding.model'),
                    $dimensions,
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // OAuth popup redirect endpoint: browser can't send Authorization header,
        // so we accept the token via ?token= query param for this one route only.
        Sanctum::getAccessTokenFromRequestUsing(function (Request $request): ?string {
            if ($request->is('api/v1/integrations/instagram/auth') && $request->filled('token')) {
                return $request->query('token');
            }

            return $request->bearerToken();
        });
    }
}
