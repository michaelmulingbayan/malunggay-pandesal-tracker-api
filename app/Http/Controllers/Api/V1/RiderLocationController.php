<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\RiderAvailabilityStatus;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RiderLocationController extends Controller
{
    /**
     * Latest known position of every active rider who has shared a location (for the admin map).
     */
    public function __invoke(): JsonResponse
    {
        Gate::authorize('viewAny', Rider::class);

        $riders = Rider::query()
            ->with(['user', 'latestLocation'])
            ->whereHas('latestLocation')
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active))
            ->get()
            ->sortByDesc(fn (Rider $rider) => $rider->latestLocation->recorded_at)
            ->values()
            ->map(fn (Rider $rider): array => [
                'rider_id' => $rider->id,
                'name' => $rider->user->name,
                'is_available' => $rider->availability_status === RiderAvailabilityStatus::Available,
                'latitude' => (float) $rider->latestLocation->latitude,
                'longitude' => (float) $rider->latestLocation->longitude,
                'recorded_at' => $rider->latestLocation->recorded_at,
            ]);

        return response()->json(['data' => $riders]);
    }
}
