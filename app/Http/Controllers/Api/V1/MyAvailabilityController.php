<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateMyAvailabilityRequest;
use App\RiderAvailabilityStatus;
use Illuminate\Http\JsonResponse;

class MyAvailabilityController extends Controller
{
    /**
     * The rider marks themselves available or unavailable for deliveries.
     */
    public function update(UpdateMyAvailabilityRequest $request): JsonResponse
    {
        $rider = $request->user()->rider;

        $rider->update([
            'availability_status' => $request->boolean('is_available')
                ? RiderAvailabilityStatus::Available
                : RiderAvailabilityStatus::Unavailable,
        ]);

        return response()->json(['data' => $rider->todaySummary()]);
    }
}
