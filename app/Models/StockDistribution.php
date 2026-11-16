<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\StockDistributionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['production_id', 'rider_id', 'quantity_distributed', 'distribution_date', 'distributed_by'])]
class StockDistribution extends Model
{
    /** @use HasFactory<StockDistributionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_distributed' => 'integer',
            'distribution_date' => 'date',
        ];
    }

    /**
     * Scope a query to distributions made on the given date.
     */
    #[Scope]
    protected function distributedOn(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('distribution_date', $date);
    }

    /**
     * Get the production batch this stock came from.
     *
     * @return BelongsTo<Production, $this>
     */
    public function production(): BelongsTo
    {
        return $this->belongsTo(Production::class);
    }

    /**
     * Get the rider who received the stock.
     *
     * @return BelongsTo<Rider, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(Rider::class);
    }

    /**
     * Get the user who distributed the stock.
     *
     * @return BelongsTo<User, $this>
     */
    public function distributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'distributed_by');
    }

    /**
     * Get the sales made from this distribution.
     *
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'distribution_id');
    }

    /**
     * Get the stock returns from this distribution.
     *
     * @return HasMany<StockReturn, $this>
     */
    public function stockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'distribution_id');
    }
}
