<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePandesalPriceRequest;
use App\Http\Resources\PandesalPriceResource;
use App\Models\PandesalPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PandesalPriceController extends Controller
{
    /**
     * Price history, newest first. The first item is the current price.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', PandesalPrice::class);

        $prices = PandesalPrice::query()
            ->with('setter')
            ->latest('id')
            ->limit(20)
            ->get();

        return PandesalPriceResource::collection($prices);
    }

    /**
     * Set a new price. It applies to sales recorded from now on; past sales keep their price.
     */
    public function store(StorePandesalPriceRequest $request): JsonResponse
    {
        $price = PandesalPrice::create([
            'price_per_piece' => $request->validated('price_per_piece'),
            'set_by' => $request->user()->id,
        ]);

        return (new PandesalPriceResource($price->load('setter')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
