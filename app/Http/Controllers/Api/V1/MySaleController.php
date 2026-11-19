<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMySaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\PandesalPrice;
use App\Models\Rider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MySaleController extends Controller
{
    /**
     * The signed-in rider's own sales history, newest first. Optionally only one day.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rider = $request->user()->rider;
        abort_if($rider === null, JsonResponse::HTTP_FORBIDDEN, 'Only riders have their own sales.');

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sales = $rider->sales()
            ->when($validated['date'] ?? null, fn ($query, string $date) => $query->whereDate('sales.sale_date', $date))
            ->latest('sales.sale_date')
            ->latest('sales.id')
            ->paginate($validated['per_page'] ?? 30);

        return SaleResource::collection($sales);
    }

    /**
     * A rider records a sale of N pieces at the current price.
     *
     * Pieces are taken from today's assignments oldest-first, so the rider never picks an assignment.
     * Responds with the rider's updated summary.
     *
     * @throws ValidationException
     */
    public function store(StoreMySaleRequest $request): JsonResponse
    {
        $rider = $request->user()->rider;
        $quantity = $request->integer('quantity_sold');

        DB::transaction(function () use ($rider, $quantity): void {
            $price = (float) PandesalPrice::current()?->price_per_piece;
            if ($price <= 0) {
                throw ValidationException::withMessages([
                    'quantity_sold' => ['No selling price has been set yet. Ask the admin to set the price per piece.'],
                ]);
            }

            $assignments = $rider->assignmentsOn(today(), lock: true);
            $remaining = $assignments->sum(fn ($assignment): int => Rider::remainingIn($assignment));

            if ($quantity > $remaining) {
                throw ValidationException::withMessages([
                    'quantity_sold' => [$remaining === 0
                        ? 'You have no stock left to sell today.'
                        : "You only have {$remaining} pcs left to sell."],
                ]);
            }

            $toSell = $quantity;
            foreach ($assignments as $assignment) {
                $take = min(Rider::remainingIn($assignment), $toSell);
                if ($take <= 0) {
                    continue;
                }

                $assignment->sales()->create([
                    'quantity_sold' => $take,
                    'price_per_piece' => $price,
                    'total_amount' => round($take * $price, 2),
                    'sale_date' => now(),
                ]);

                $toSell -= $take;
                if ($toSell === 0) {
                    break;
                }
            }
        });

        return response()->json(['data' => $rider->todaySummary()], JsonResponse::HTTP_CREATED);
    }
}
