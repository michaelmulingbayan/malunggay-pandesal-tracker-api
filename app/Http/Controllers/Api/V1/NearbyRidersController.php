<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\RiderAvailabilityStatus;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class NearbyRidersController extends Controller
{
    /**
     * Only locations shared within this many minutes count as "on the road now".
     */
    private const FRESH_MINUTES = 30;

    /**
     * Public (no login): riders customers can find right now.
     *
     * Only active, available riders who shared a location recently are listed, with their
     * first name only (no contact details). Distance is computed on the customer's phone,
     * so the customer's own location is never sent here.
     */
    public function __invoke(): JsonResponse
    {
        $freshSince = now()->subMinutes(self::FRESH_MINUTES);

        $riders = Rider::query()
            ->with(['user', 'latestLocation'])
            ->where('availability_status', RiderAvailabilityStatus::Available)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::Active))
            ->whereHas('latestLocation', fn ($query) => $query->where('recorded_at', '>=', $freshSince))
            ->get()
            // whereHas checks "any" location; make sure the *latest* one is the fresh one.
            ->filter(fn (Rider $rider): bool => $rider->latestLocation?->recorded_at?->gte($freshSince) ?? false)
            ->map(fn (Rider $rider): array => [
                'rider_id' => $rider->id,
                'first_name' => Str::of($rider->user->name)->trim()->before(' ')->toString(),
                'latitude' => (float) $rider->latestLocation->latitude,
                'longitude' => (float) $rider->latestLocation->longitude,
                'recorded_at' => $rider->latestLocation->recorded_at,
            ])
            ->values();

        return response()->json(['data' => $riders]);
    }
}
