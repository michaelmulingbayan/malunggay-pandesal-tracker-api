<?php

namespace App\Models;

use App\RiderAvailabilityStatus;
use App\StockReturnStatus;
use Carbon\CarbonInterface;
use Database\Factories\RiderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'phone_number', 'address', 'availability_status'])]
class Rider extends Model
{
    /** @use HasFactory<RiderFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'availability_status' => RiderAvailabilityStatus::class,
        ];
    }

    /**
     * Get the user account of the rider.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the location history of the rider.
     *
     * @return HasMany<RiderLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(RiderLocation::class);
    }

    /**
     * Get the most recently recorded location of the rider.
     *
     * @return HasOne<RiderLocation, $this>
     */
    public function latestLocation(): HasOne
    {
        return $this->hasOne(RiderLocation::class)->latestOfMany('recorded_at');
    }

    /**
     * Get the stock distributions assigned to the rider.
     *
     * @return HasMany<StockDistribution, $this>
     */
    public function stockDistributions(): HasMany
    {
        return $this->hasMany(StockDistribution::class);
    }

    /**
     * Get every sale made from the rider's assigned stock.
     *
     * @return HasManyThrough<Sale, StockDistribution, $this>
     */
    public function sales(): HasManyThrough
    {
        return $this->hasManyThrough(Sale::class, StockDistribution::class, 'rider_id', 'distribution_id');
    }

    /**
     * The rider's assignments for a date, oldest first, with `sold_quantity` and
     * `returned_quantity` (rejected returns excluded). Remaining = distributed − sold − returned.
     *
     * @return Collection<int, StockDistribution>
     */
    public function assignmentsOn(CarbonInterface|string $date, bool $lock = false): Collection
    {
        return $this->stockDistributions()
            ->distributedOn($date)
            ->withSum('sales as sold_quantity', 'quantity_sold')
            ->withSum(
                ['stockReturns as returned_quantity' => fn ($query) => $query->where('status', '!=', StockReturnStatus::Rejected)],
                'quantity_returned',
            )
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    /**
     * Pieces of an assignment (loaded via assignmentsOn) the rider still holds.
     */
    public static function remainingIn(StockDistribution $assignment): int
    {
        return max(0, $assignment->quantity_distributed
            - (int) $assignment->sold_quantity
            - (int) $assignment->returned_quantity);
    }

    /**
     * Today's stock and sales for the rider app: totals, current price, sales and returns.
     *
     * @return array<string, mixed>
     */
    public function todaySummary(): array
    {
        $date = today();
        $assignments = $this->assignmentsOn($date);
        $assignmentIds = $assignments->pluck('id');

        $assigned = (int) $assignments->sum('quantity_distributed');
        $sold = (int) $assignments->sum('sold_quantity');
        $returned = (int) $assignments->sum('returned_quantity');

        $sales = Sale::query()->whereIn('distribution_id', $assignmentIds)->latest('id')->get();
        $returns = StockReturn::query()->whereIn('distribution_id', $assignmentIds)->latest('id')->get();

        $lastLocation = $this->latestLocation()->first();

        return [
            'date' => $date->toDateString(),
            'is_available' => $this->availability_status === RiderAvailabilityStatus::Available,
            'last_location' => $lastLocation ? [
                'latitude' => (float) $lastLocation->latitude,
                'longitude' => (float) $lastLocation->longitude,
                'recorded_at' => $lastLocation->recorded_at,
            ] : null,
            'price_per_piece' => PandesalPrice::current()?->price_per_piece,
            'assigned' => $assigned,
            'sold' => $sold,
            'returned' => $returned,
            'remaining' => max(0, $assigned - $sold - $returned),
            'sales_amount' => number_format((float) $sales->sum('total_amount'), 2, '.', ''),
            'sales' => $sales->map(fn (Sale $sale): array => [
                'id' => $sale->id,
                'quantity_sold' => $sale->quantity_sold,
                'price_per_piece' => $sale->price_per_piece,
                'total_amount' => $sale->total_amount,
                'sale_date' => $sale->sale_date,
            ])->values(),
            'returns' => $returns->map(fn (StockReturn $return): array => [
                'id' => $return->id,
                'quantity_returned' => $return->quantity_returned,
                'status' => $return->status,
                'return_date' => $return->return_date,
            ])->values(),
        ];
    }
}
