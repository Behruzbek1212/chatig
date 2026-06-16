<?php

use App\Http\Middleware\ResolveStore;
use App\Http\Middleware\ResolveStoreFromPublicId;
use App\Http\Middleware\VerifyTelegramInitData;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'store' => ResolveStore::class,
            'telegram.init-data' => VerifyTelegramInitData::class,
            'store.public' => ResolveStoreFromPublicId::class,
        ]);

        // API is stateless — never redirect guests to a `login` page. Returning
        // null makes the auth middleware throw AuthenticationException, which we
        // render as a clean 401 JSON below (instead of crashing on route('login')).
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // API is stateless: an unauthenticated request must always get a clean
        // 401 JSON, never a redirect to the (non-existent) `login` named route —
        // which otherwise crashes as a 500 when the Accept header is missing.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return new JsonResponse(['message' => 'Avtorizatsiya talab qilinadi.'], 401);
            }

            return null;
        });
    })->create();
