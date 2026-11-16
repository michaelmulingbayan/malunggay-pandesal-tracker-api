<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['production_date', 'quantity_produced', 'remarks', 'recorded_by'])]
class Production extends Model
{
    /** @use HasFactory<ProductionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'production_date' => 'date',
            'quantity_produced' => 'integer',
        ];
    }

    /**
     * Scope a query to productions made on the given date.
     */
    #[Scope]
    protected function producedOn(Builder $query, CarbonInterface|string $date): void
    {
        $query->whereDate('production_date', $date);
    }

    /**
     * Get the user who recorded the production.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Get the stock distributions taken from this production.
     *
     * @return HasMany<StockDistribution, $this>
     */
    public function stockDistributions(): HasMany
    {
        return $this->hasMany(StockDistribution::class);
    }
}
