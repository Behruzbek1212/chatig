<?php

namespace App\Providers;

use App\Services\Llm\Contracts\LlmClient;
use App\Services\Llm\FakeLlmClient;
use App\Services\Llm\OpenAiClient;
use App\Services\Sms\Contracts\SmsSender;
use App\Services\Sms\EskizSmsSender;
use App\Services\Sms\LogSmsSender;
use App\Support\Tenancy;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
