<?php

namespace App\Models;

use Database\Factories\PandesalPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The selling price of one pandesal. Each change is a new row; the newest row is current.
 */
#[Fillable(['price_per_piece', 'set_by'])]
class PandesalPrice extends Model
{
    /** @use HasFactory<PandesalPriceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_per_piece' => 'decimal:2',
        ];
    }

    /**
     * The price in effect right now, or null if no price has been set yet.
     */
    public static function current(): ?self
    {
        return static::query()->latest('id')->first();
    }

    /**
     * Get the admin who set this price.
     *
     * @return BelongsTo<User, $this>
     */
    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
