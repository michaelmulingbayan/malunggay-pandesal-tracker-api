<?php

namespace App\Models;

use App\RiderAvailabilityStatus;
use Database\Factories\RiderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
