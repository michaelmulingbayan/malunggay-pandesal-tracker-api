<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['distribution_id', 'quantity_sold', 'price_per_piece', 'total_amount', 'sale_date'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_sold' => 'integer',
            'price_per_piece' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sale_date' => 'datetime',
        ];
    }

    /**
     * Get the stock distribution this sale was made from.
     *
     * @return BelongsTo<StockDistribution, $this>
     */
    public function stockDistribution(): BelongsTo
    {
        return $this->belongsTo(StockDistribution::class, 'distribution_id');
    }
}
