<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\UserRole;
use App\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the rider profile belonging to the user.
     *
     * @return HasOne<Rider, $this>
     */
    public function rider(): HasOne
    {
        return $this->hasOne(Rider::class);
    }

    /**
     * Get the productions recorded by the user.
     *
     * @return HasMany<Production, $this>
     */
    public function productions(): HasMany
    {
        return $this->hasMany(Production::class, 'recorded_by');
    }

    /**
     * Get the stock distributions made by the user.
     *
     * @return HasMany<StockDistribution, $this>
     */
    public function stockDistributions(): HasMany
    {
        return $this->hasMany(StockDistribution::class, 'distributed_by');
    }

    /**
     * Get the inventory adjustments recorded by the user.
     *
     * @return HasMany<InventoryAdjustment, $this>
     */
    public function inventoryAdjustments(): HasMany
    {
        return $this->hasMany(InventoryAdjustment::class, 'recorded_by');
    }

    /**
     * Get the stock returns verified by the user.
     *
     * @return HasMany<StockReturn, $this>
     */
    public function verifiedStockReturns(): HasMany
    {
        return $this->hasMany(StockReturn::class, 'verified_by');
    }

    /**
     * Determine whether the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
