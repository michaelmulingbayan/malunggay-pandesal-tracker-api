<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\Models\StockReturn;
use App\StockReturnStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    /**
     * Stock flow for a day (default today): produced → distributed to riders → returned unsold.
     *
     * `returned` counts pending and verified returns (rejected ones are excluded);
     * `returned_pending` is the part still waiting for an admin to verify.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', StockReturn::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? today()->toDateString();

        $produced = (int) Production::query()->producedOn($date)->sum('quantity_produced');
        $distributed = (int) StockDistribution::query()->distributedOn($date)->sum('quantity_distributed');

        $returns = StockReturn::query()
            ->whereHas('stockDistribution', fn ($query) => $query->distributedOn($date))
            ->where('status', '!=', StockReturnStatus::Rejected);

        $returned = (int) (clone $returns)->sum('quantity_returned');
        $returnedPending = (int) (clone $returns)->where('status', StockReturnStatus::Pending)->sum('quantity_returned');

        $sold = (int) Sale::query()
            ->whereHas('stockDistribution', fn ($query) => $query->distributedOn($date))
            ->sum('quantity_sold');

        return response()->json([
            'data' => [
                'date' => $date,
                'produced' => $produced,
                'distributed' => $distributed,
                'sold' => $sold,
                'returned' => $returned,
                'returned_verified' => $returned - $returnedPending,
                'returned_pending' => $returnedPending,
                // Distributed stock not yet sold or returned (still out with riders).
                'with_riders' => max(0, $distributed - $sold - $returned),
                'undistributed' => max(0, $produced - $distributed),
            ],
        ]);
    }
}
