<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProductionRequest;
use App\Http\Resources\ProductionResource;
use App\Models\Production;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductionController extends Controller
{
    /**
     * Production history, newest first (paginated).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Production::class);

        $productions = Production::query()
            ->with('recorder')
            ->latest('production_date')
            ->latest('id')
            ->paginate(max(1, min($request->integer('per_page', 30), 100)));

        return ProductionResource::collection($productions);
    }

    /**
     * Record a production batch for the given date (usually today).
     */
    public function store(StoreProductionRequest $request): JsonResponse
    {
        $production = $request->user()->productions()->create($request->validated());

        return (new ProductionResource($production->load('recorder')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
