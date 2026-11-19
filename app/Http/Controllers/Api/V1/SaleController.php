<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\PandesalPrice;
use App\Models\Sale;
use App\Models\StockDistribution;
use App\StockReturnStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Every recorded sale with its rider, newest first. Optionally only one day and/or one rider.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Sale::class);

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'rider_id' => ['nullable', 'integer', 'exists:riders,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $sales = Sale::query()
            ->with('stockDistribution.rider.user')
            ->when($validated['date'] ?? null, fn ($query, string $date) => $query->whereDate('sale_date', $date))
            ->when($validated['rider_id'] ?? null, fn ($query, int $riderId) => $query->whereHas(
                'stockDistribution',
                fn ($distribution) => $distribution->where('rider_id', $riderId),
            ))
            ->latest('sale_date')
            ->latest('id')
            ->paginate($validated['per_page'] ?? 30);

        return SaleResource::collection($sales);
    }

    /**
     * Record a sale from a rider's assigned stock.
     *
     * The price is the current admin-set price (admins may override it); the total is calculated here.
     *
     * @throws ValidationException
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = DB::transaction(function () use ($request): Sale {
            $distribution = StockDistribution::query()
                ->lockForUpdate()
                ->findOrFail($request->integer('distribution_id'));

            $returned = (int) $distribution->stockReturns()
                ->where('status', '!=', StockReturnStatus::Rejected)
                ->sum('quantity_returned');
            $alreadySold = (int) $distribution->sales()->sum('quantity_sold');
            $sellable = $distribution->quantity_distributed - $returned - $alreadySold;

            $quantity = $request->integer('quantity_sold');

            if ($quantity > $sellable) {
                throw ValidationException::withMessages([
                    'quantity_sold' => ["Only {$sellable} pcs from this assignment are left to sell."],
                ]);
            }

            $price = $request->filled('price_per_piece')
                ? (float) $request->input('price_per_piece')
                : (float) PandesalPrice::current()?->price_per_piece;

            if ($price <= 0) {
                throw ValidationException::withMessages([
                    'price_per_piece' => ['No selling price has been set yet. Ask the admin to set the price per piece.'],
                ]);
            }

            return $distribution->sales()->create([
                'quantity_sold' => $quantity,
                'price_per_piece' => $price,
                'total_amount' => round($quantity * $price, 2),
                'sale_date' => now(),
            ]);
        });

        return (new SaleResource($sale->load('stockDistribution.rider.user')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
