<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeadController extends ApiController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $leads = Lead::query()
            ->when($request->string('period')->value() === 'today',
                fn ($q) => $q->whereDate('created_at', today()))
            ->when($request->string('period')->value() === 'week',
                fn ($q) => $q->where('created_at', '>=', now()->subWeek()))
            ->when($request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = $request->string('q');
                $q->where(fn ($sub) => $sub
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
            })
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return LeadResource::collection($leads);
    }

    public function show(Lead $lead): JsonResponse
    {
        return $this->ok(new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $lead->update($request->validated());

        return $this->ok(new LeadResource($lead));
    }
}
