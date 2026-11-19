<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PiecesSoldReportController extends Controller
{
    private const int DAYS = 7;

    private const int WEEKS = 8;

    private const int MONTHS = 6;

    /**
     * Pieces sold and sales amount per day (last 7 days), per week (last 8 weeks, Monday–Sunday)
     * or per month (last 6 months), oldest first.
     *
     * Every day/week/month in the range is listed, even without sales, so the chart never has gaps.
     * The last point is today / this week / this month; `start` is its first day.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
        ]);
        $period = $validated['period'] ?? 'daily';

        $today = CarbonImmutable::today();
        [$from, $count, $step] = match ($period) {
            'weekly' => [$today->startOfWeek(CarbonImmutable::MONDAY)->subWeeks(self::WEEKS - 1), self::WEEKS, 'addWeeks'],
            'monthly' => [$today->startOfMonth()->subMonths(self::MONTHS - 1), self::MONTHS, 'addMonths'],
            default => [$today->subDays(self::DAYS - 1), self::DAYS, 'addDays'],
        };
        $bucketOf = fn (CarbonImmutable $date): string => match ($period) {
            'weekly' => $date->startOfWeek(CarbonImmutable::MONDAY)->toDateString(),
            'monthly' => $date->startOfMonth()->toDateString(),
            default => $date->toDateString(),
        };

        $totals = Sale::query()
            ->where('sale_date', '>=', $from)
            ->where('sale_date', '<', $today->addDay())
            ->get(['sale_date', 'quantity_sold', 'total_amount'])
            ->groupBy(fn (Sale $sale): string => $bucketOf(CarbonImmutable::instance($sale->sale_date)));

        $points = collect(range(0, $count - 1))
            ->map(function (int $offset) use ($from, $step, $totals): array {
                $start = $from->{$step}($offset)->toDateString();
                $sales = $totals->get($start, collect());

                return [
                    'start' => $start,
                    'pieces_sold' => (int) $sales->sum('quantity_sold'),
                    'total_amount' => $this->money($sales->sum(fn (Sale $sale): float => (float) $sale->total_amount)),
                ];
            });

        return response()->json([
            'data' => [
                'period' => $period,
                'from' => $from->toDateString(),
                'to' => $today->toDateString(),
                'pieces_sold' => $points->sum('pieces_sold'),
                'total_amount' => $this->money($points->sum(fn (array $point): float => (float) $point['total_amount'])),
                'points' => $points->values(),
            ],
        ]);
    }

    private function money(float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
