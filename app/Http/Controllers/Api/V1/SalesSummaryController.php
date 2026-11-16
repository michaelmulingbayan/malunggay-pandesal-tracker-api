<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SalesSummaryController extends Controller
{
    /**
     * Sales for a day (default today): totals plus one row per rider, highest sales first.
     *
     * Money is returned as a 2-decimal string (e.g. "1200.00") so clients never round it.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Sale::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $date = $validated['date'] ?? today()->toDateString();

        $perRider = Sale::query()
            ->whereDate('sales.sale_date', $date)
            ->join('stock_distributions', 'stock_distributions.id', '=', 'sales.distribution_id')
            ->join('riders', 'riders.id', '=', 'stock_distributions.rider_id')
            ->join('users', 'users.id', '=', 'riders.user_id')
            ->groupBy('riders.id', 'users.name')
            ->select('riders.id as rider_id', 'users.name as rider_name')
            ->selectRaw('SUM(sales.quantity_sold) as pieces_sold')
            ->selectRaw('SUM(sales.total_amount) as total_amount')
            ->selectRaw('COUNT(*) as sales_count')
            ->orderByDesc('total_amount')
            ->get()
            ->map(fn (Sale $row): array => [
                'rider_id' => (int) $row->rider_id,
                'rider_name' => $row->rider_name,
                'pieces_sold' => (int) $row->pieces_sold,
                'total_amount' => $this->money($row->total_amount),
                'sales_count' => (int) $row->sales_count,
            ]);

        return response()->json([
            'data' => [
                'date' => $date,
                'total_amount' => $this->money($perRider->sum(fn (array $row): float => (float) $row['total_amount'])),
                'pieces_sold' => $perRider->sum('pieces_sold'),
                'riders_count' => $perRider->count(),
                'riders' => $perRider->values(),
            ],
        ]);
    }

    private function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
