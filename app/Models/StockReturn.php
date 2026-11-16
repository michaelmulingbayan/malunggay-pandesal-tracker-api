<?php

namespace App\Models;

use App\StockReturnStatus;
use Database\Factories\StockReturnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['distribution_id', 'quantity_returned', 'return_date', 'status', 'verified_by'])]
class StockReturn extends Model
{
    /** @use HasFactory<StockReturnFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_returned' => 'integer',
            'return_date' => 'datetime',
            'status' => StockReturnStatus::class,
        ];
    }

    /**
     * Get the stock distribution this return belongs to.
     *
     * @return BelongsTo<StockDistribution, $this>
     */
    public function stockDistribution(): BelongsTo
    {
        return $this->belongsTo(StockDistribution::class, 'distribution_id');
    }

    /**
     * Get the user who verified the return.
     *
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
