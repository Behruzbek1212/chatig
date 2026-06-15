<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Ai\GeneratePromptRequest;
use App\Http\Requests\Ai\TestPromptRequest;
use App\Http\Requests\Ai\UpdateAiSettingsRequest;
use App\Http\Resources\AiConfigResource;
use App\Services\Auth\Ai\AiSettingsService;
use App\Services\Auth\Ai\PromptGeneratorService;
use App\Services\Llm\Contracts\LlmClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSettingsController extends ApiController
{
    public function __construct(
        private readonly PromptGeneratorService $generator,
        private readonly AiSettingsService $settings,
        private readonly LlmClient $llm,
    ) {}

    public function generate(GeneratePromptRequest $request): JsonResponse
    {
        $prompt = $this->generator->generate($request->user()->store, $request->validated());

        return $this->ok(['generated_prompt' => $prompt]);
    }

    public function test(TestPromptRequest $request): JsonResponse
    {
        $reply = $this->llm->chat(
            config('chatig.llm.models.sales'),
            [
                ['role' => 'system', 'content' => $request->string('prompt')->value()],
                ['role' => 'user', 'content' => $request->string('message')->value()],
            ],
        );

        return $this->ok(['reply' => $reply]);
    }

    public function show(Request $request): JsonResponse
    {
        $config = $this->settings->current($request->user()->store);

        return $this->ok($config ? new AiConfigResource($config) : null);
    }

    public function update(UpdateAiSettingsRequest $request): JsonResponse
    {
        $config = $this->settings->save($request->user()->store, $request->validated());

        return $this->ok(new AiConfigResource($config));
    }
}
