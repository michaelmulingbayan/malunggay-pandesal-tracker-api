<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreStockDistributionRequest;
use App\Http\Resources\StockDistributionResource;
use App\Models\Production;
use App\Models\StockDistribution;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StockDistributionController extends Controller
{
    /**
     * Stock assigned to riders on a date (default today), with that day's stock summary in `meta`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', StockDistribution::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? today()->toDateString();

        $distributions = StockDistribution::query()
            ->distributedOn($date)
            ->with(['rider.user', 'distributor'])
            ->latest('id')
            ->get();

        return StockDistributionResource::collection($distributions)
            ->additional(['meta' => $this->stockSummary($date)]);
    }

    /**
     * Assign today's stock to a rider.
     *
     * Stock is taken from today's production batches in the order they were recorded,
     * so a large assignment may span more than one batch (one row per batch).
     *
     * @throws ValidationException
     */
    public function store(StoreStockDistributionRequest $request): JsonResponse
    {
        $date = today();
        $quantity = $request->integer('quantity');

        $distributions = DB::transaction(function () use ($request, $date, $quantity): Collection {
            $batches = Production::query()
                ->producedOn($date)
                ->withSum('stockDistributions as distributed_quantity', 'quantity_distributed')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $available = $batches->sum(fn (Production $batch): int => $this->remainingIn($batch));

            if ($quantity > $available) {
                throw ValidationException::withMessages([
                    'quantity' => [$available === 0
                        ? 'There is no stock left to distribute today. Record production first.'
                        : "Only {$available} pcs are available to distribute."],
                ]);
            }

            $created = new Collection;
            $remaining = $quantity;

            foreach ($batches as $batch) {
                $take = min($this->remainingIn($batch), $remaining);

                if ($take <= 0) {
                    continue;
                }

                $created->push($batch->stockDistributions()->create([
                    'rider_id' => $request->integer('rider_id'),
                    'quantity_distributed' => $take,
                    'distribution_date' => $date,
                    'distributed_by' => $request->user()->id,
                ]));

                $remaining -= $take;

                if ($remaining === 0) {
                    break;
                }
            }

            return $created;
        });

        return StockDistributionResource::collection($distributions->load(['rider.user', 'distributor']))
            ->additional(['meta' => $this->stockSummary($date)])
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    /**
     * Pieces of a batch not yet assigned to any rider.
     */
    private function remainingIn(Production $batch): int
    {
        return $batch->quantity_produced - (int) $batch->distributed_quantity;
    }

    /**
     * Produced, distributed and still-available pieces for a date.
     *
     * @return array{date: string, produced: int, distributed: int, available: int}
     */
    private function stockSummary(CarbonInterface|string $date): array
    {
        $produced = (int) Production::query()->producedOn($date)->sum('quantity_produced');

        $distributed = (int) StockDistribution::query()
            ->whereHas('production', fn ($query) => $query->producedOn($date))
            ->sum('quantity_distributed');

        return [
            'date' => $date instanceof CarbonInterface ? $date->toDateString() : $date,
            'produced' => $produced,
            'distributed' => $distributed,
            'available' => max(0, $produced - $distributed),
        ];
    }
}
