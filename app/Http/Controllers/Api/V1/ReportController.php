<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ReportPeriodRequest;
use App\Models\Production;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\StockReturnStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * Detailed admin reports for a day, a week (Monday–Sunday) or a month — see {@see ReportPeriodRequest}.
 *
 * Every report starts with `period`, `date`, `from` and `to`, then a `summary`, a `daily` breakdown with one
 * row per day of the period (no gaps), and report-specific lists. Money is a 2-decimal string.
 *
 * Production and distribution count on their own dates. Sales count on the day they were made. Sold / returned
 * pieces in the inventory, distribution and rider reports belong to the day the stock was handed out, the same
 * way the Stock tab counts them.
 */
class ReportController extends Controller
{
    /**
     * Batches baked in the period and how much of them went out to riders.
     */
    public function production(ReportPeriodRequest $request): JsonResponse
    {
        $batches = Production::query()
            ->with('recorder')
            ->withSum('stockDistributions as distributed_quantity', 'quantity_distributed')
            ->whereDate('production_date', '>=', $request->from())
            ->whereDate('production_date', '<=', $request->to())
            ->orderByDesc('production_date')
            ->orderByDesc('id')
            ->get();

        $produced = (int) $batches->sum('quantity_produced');
        $distributed = (int) $batches->sum('distributed_quantity');
        $productionDays = $batches->map(fn (Production $batch): string => $batch->production_date->toDateString())->unique()->count();
        $byDay = $batches->groupBy(fn (Production $batch): string => $batch->production_date->toDateString());

        return $this->respond($request, [
            'summary' => [
                'produced' => $produced,
                'batches' => $batches->count(),
                'production_days' => $productionDays,
                'average_per_production_day' => $productionDays > 0 ? intdiv($produced, $productionDays) : 0,
                'distributed' => $distributed,
                'undistributed' => max(0, $produced - $distributed),
            ],
            'daily' => $this->perDay($request, fn (string $day): array => [
                'produced' => (int) $byDay->get($day, collect())->sum('quantity_produced'),
            ]),
            'batches' => $batches->map(fn (Production $batch): array => [
                'id' => $batch->id,
                'production_date' => $batch->production_date->toDateString(),
                'quantity_produced' => $batch->quantity_produced,
                'distributed' => (int) $batch->distributed_quantity,
                'remarks' => $batch->remarks,
                'recorded_by' => $batch->recorder?->name,
            ])->values(),
        ]);
    }

    /**
     * Money in: sales made in the period, per day and per rider.
     */
    public function sales(ReportPeriodRequest $request): JsonResponse
    {
        $sales = Sale::query()
            ->with('stockDistribution.rider.user')
            ->where('sale_date', '>=', $request->from())
            ->where('sale_date', '<', $request->to()->addDay())
            ->get();

        $pieces = (int) $sales->sum('quantity_sold');
        $amount = $this->sumMoney($sales);
        $byDay = $sales->groupBy(fn (Sale $sale): string => $sale->sale_date->toDateString());

        $riders = $sales->groupBy(fn (Sale $sale): int => $sale->stockDistribution->rider_id)
            ->map(fn (Collection $riderSales): array => [
                'rider_id' => $riderSales->first()->stockDistribution->rider_id,
                'rider_name' => $riderSales->first()->stockDistribution->rider->user->name,
                'pieces_sold' => (int) $riderSales->sum('quantity_sold'),
                'total_amount' => $this->money($this->sumMoney($riderSales)),
                'sales_count' => $riderSales->count(),
            ])
            ->sortByDesc(fn (array $row): float => (float) $row['total_amount'])
            ->values();

        return $this->respond($request, [
            'summary' => [
                'total_amount' => $this->money($amount),
                'pieces_sold' => $pieces,
                'sales_count' => $sales->count(),
                'riders_count' => $riders->count(),
                'average_price' => $this->money($pieces > 0 ? $amount / $pieces : 0),
            ],
            'daily' => $this->perDay($request, fn (string $day): array => [
                'pieces_sold' => (int) $byDay->get($day, collect())->sum('quantity_sold'),
                'total_amount' => $this->money($this->sumMoney($byDay->get($day, collect()))),
            ]),
            'riders' => $riders,
        ]);
    }

    /**
     * Stock flow: produced → distributed → sold / returned / still with riders, and what never left the bakery.
     */
    public function inventory(ReportPeriodRequest $request): JsonResponse
    {
        $produced = Production::query()
            ->whereDate('production_date', '>=', $request->from())
            ->whereDate('production_date', '<=', $request->to())
            ->get(['production_date', 'quantity_produced'])
            ->groupBy(fn (Production $batch): string => $batch->production_date->toDateString());

        $assignments = $this->assignmentsIn($request);
        $byDay = $assignments->groupBy(fn (StockDistribution $assignment): string => $assignment->distribution_date->toDateString());

        $totalProduced = (int) $produced->flatten()->sum('quantity_produced');
        $flow = $this->flow($assignments);

        return $this->respond($request, [
            'summary' => [
                'produced' => $totalProduced,
                'distributed' => $flow['distributed'],
                'sold' => $flow['sold'],
                'returned' => $flow['returned'],
                'returned_verified' => (int) $assignments->sum('verified_quantity'),
                'returned_pending' => (int) $assignments->sum('pending_quantity'),
                'returned_rejected' => (int) $assignments->sum('rejected_quantity'),
                'with_riders' => $flow['remaining'],
                'undistributed' => max(0, $totalProduced - $flow['distributed']),
                'sell_through_percent' => $this->percent($flow['sold'], $flow['distributed']),
            ],
            'daily' => $this->perDay($request, function (string $day) use ($produced, $byDay): array {
                $flow = $this->flow($byDay->get($day, new Collection));

                return [
                    'produced' => (int) $produced->get($day, collect())->sum('quantity_produced'),
                    'distributed' => $flow['distributed'],
                    'sold' => $flow['sold'],
                    'returned' => $flow['returned'],
                ];
            }),
        ]);
    }

    /**
     * Stock handed out to riders: per rider and every assignment, with what came of it.
     */
    public function distribution(ReportPeriodRequest $request): JsonResponse
    {
        $assignments = $this->assignmentsIn($request);
        $byDay = $assignments->groupBy(fn (StockDistribution $assignment): string => $assignment->distribution_date->toDateString());
        $flow = $this->flow($assignments);

        $riders = $assignments->groupBy('rider_id')
            ->map(fn (Collection $riderAssignments): array => [
                'rider_id' => $riderAssignments->first()->rider_id,
                'rider_name' => $riderAssignments->first()->rider->user->name,
                'assignments' => $riderAssignments->count(),
                ...$this->flow($riderAssignments),
            ])
            ->sortByDesc('distributed')
            ->values();

        return $this->respond($request, [
            'summary' => [
                'distributed' => $flow['distributed'],
                'assignments' => $assignments->count(),
                'riders_count' => $riders->count(),
                'sold' => $flow['sold'],
                'returned' => $flow['returned'],
                'with_riders' => $flow['remaining'],
            ],
            'daily' => $this->perDay($request, fn (string $day): array => [
                'distributed' => (int) $byDay->get($day, collect())->sum('quantity_distributed'),
            ]),
            'riders' => $riders,
            'assignments' => $assignments
                ->sortByDesc(fn (StockDistribution $assignment): string => $assignment->distribution_date->toDateString().'-'.str_pad((string) $assignment->id, 10, '0', STR_PAD_LEFT))
                ->take(200)
                ->map(fn (StockDistribution $assignment): array => [
                    'id' => $assignment->id,
                    'distribution_date' => $assignment->distribution_date->toDateString(),
                    'rider_name' => $assignment->rider->user->name,
                    ...$this->flow(new Collection([$assignment])),
                ])
                ->values(),
        ]);
    }

    /**
     * How well each rider sold the stock they were given, best sales first.
     */
    public function riders(ReportPeriodRequest $request): JsonResponse
    {
        $assignments = $this->assignmentsIn($request);
        $flow = $this->flow($assignments);

        $riders = $assignments->groupBy('rider_id')
            ->map(function (Collection $riderAssignments): array {
                $flow = $this->flow($riderAssignments);
                $daysActive = $riderAssignments
                    ->map(fn (StockDistribution $assignment): string => $assignment->distribution_date->toDateString())
                    ->unique()
                    ->count();

                return [
                    'rider_id' => $riderAssignments->first()->rider_id,
                    'rider_name' => $riderAssignments->first()->rider->user->name,
                    'days_active' => $daysActive,
                    'assigned' => $flow['distributed'],
                    'sold' => $flow['sold'],
                    'returned' => $flow['returned'],
                    'remaining' => $flow['remaining'],
                    'sales_amount' => $this->money((float) $riderAssignments->sum('sales_amount')),
                    'sell_through_percent' => $this->percent($flow['sold'], $flow['distributed']),
                    'average_sold_per_day' => $daysActive > 0 ? intdiv($flow['sold'], $daysActive) : 0,
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['sales_amount'])
            ->values();

        return $this->respond($request, [
            'summary' => [
                'riders_count' => $riders->count(),
                'assigned' => $flow['distributed'],
                'sold' => $flow['sold'],
                'sales_amount' => $this->money((float) $assignments->sum('sales_amount')),
                'sell_through_percent' => $this->percent($flow['sold'], $flow['distributed']),
                'top_rider_name' => $riders->first()['rider_name'] ?? null,
            ],
            'riders' => $riders,
        ]);
    }

    /**
     * Assignments handed out in the period with what was sold / returned from them and the sales amount.
     *
     * @return Collection<int, StockDistribution>
     */
    private function assignmentsIn(ReportPeriodRequest $request): Collection
    {
        $status = fn (StockReturnStatus ...$statuses) => fn ($query) => $query->whereIn('status', $statuses);

        return StockDistribution::query()
            ->with('rider.user')
            ->withSum('sales as sold_quantity', 'quantity_sold')
            ->withSum('sales as sales_amount', 'total_amount')
            ->withSum(['stockReturns as verified_quantity' => $status(StockReturnStatus::Verified)], 'quantity_returned')
            ->withSum(['stockReturns as pending_quantity' => $status(StockReturnStatus::Pending)], 'quantity_returned')
            ->withSum(['stockReturns as rejected_quantity' => $status(StockReturnStatus::Rejected)], 'quantity_returned')
            ->whereDate('distribution_date', '>=', $request->from())
            ->whereDate('distribution_date', '<=', $request->to())
            ->get();
    }

    /**
     * Distributed, sold, returned (pending + verified) and remaining for some assignments.
     *
     * @param  Collection<int, StockDistribution>  $assignments
     * @return array{distributed: int, sold: int, returned: int, remaining: int}
     */
    private function flow(Collection $assignments): array
    {
        $distributed = (int) $assignments->sum('quantity_distributed');
        $sold = (int) $assignments->sum('sold_quantity');
        $returned = (int) $assignments->sum('verified_quantity') + (int) $assignments->sum('pending_quantity');

        return [
            'distributed' => $distributed,
            'sold' => $sold,
            'returned' => $returned,
            'remaining' => max(0, $distributed - $sold - $returned),
        ];
    }

    /**
     * One row per day of the period: `date` plus whatever [$values] returns for that day.
     *
     * @param  callable(string): array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    private function perDay(ReportPeriodRequest $request, callable $values): array
    {
        return array_map(fn (string $day): array => ['date' => $day, ...$values($day)], $request->days());
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function respond(ReportPeriodRequest $request, array $report): JsonResponse
    {
        return response()->json(['data' => [...$request->periodFields(), ...$report]]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Sale>  $sales
     */
    private function sumMoney(\Illuminate\Support\Collection $sales): float
    {
        return (float) $sales->sum(fn (Sale $sale): float => (float) $sale->total_amount);
    }

    private function percent(int $part, int $whole): int
    {
        return $whole > 0 ? (int) round($part * 100 / $whole) : 0;
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
