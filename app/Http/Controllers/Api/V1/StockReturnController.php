<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockReturnRequest;
use App\Http\Resources\StockReturnResource;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\StockReturnStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StockReturnController extends Controller
{
    /**
     * Stock returns: pending first (they need action), then newest first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockReturn::class);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(StockReturnStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $returns = StockReturn::query()
            ->with(['stockDistribution.rider.user', 'verifier'])
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StockReturnStatus::Pending->value])
            ->latest('return_date')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 30);

        return StockReturnResource::collection($returns);
    }

    /**
     * Record unsold stock coming back from a rider. It stays pending until an admin verifies it.
     *
     * @throws ValidationException
     */
    public function store(StoreStockReturnRequest $request): JsonResponse
    {
        $stockReturn = DB::transaction(function () use ($request): StockReturn {
            $distribution = StockDistribution::query()
                ->lockForUpdate()
                ->findOrFail($request->integer('distribution_id'));

            $alreadyReturned = (int) $distribution->stockReturns()
                ->where('status', '!=', StockReturnStatus::Rejected)
                ->sum('quantity_returned');
            $returnable = $distribution->quantity_distributed - $alreadyReturned;

            if ($request->integer('quantity_returned') > $returnable) {
                throw ValidationException::withMessages([
                    'quantity_returned' => ["Only {$returnable} pcs from this assignment can still be returned."],
                ]);
            }

            return $distribution->stockReturns()->create([
                'quantity_returned' => $request->integer('quantity_returned'),
                'return_date' => now(),
                'status' => StockReturnStatus::Pending,
            ]);
        });

        return (new StockReturnResource($stockReturn->load(['stockDistribution.rider.user', 'verifier'])))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
