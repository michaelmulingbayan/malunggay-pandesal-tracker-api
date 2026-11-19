<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreMyLocationRequest;
use Illuminate\Http\JsonResponse;

class MyLocationController extends Controller
{
    /**
     * Store a GPS point shared by the rider's app. The server stamps the time.
     */
    public function store(StoreMyLocationRequest $request): JsonResponse
    {
        $location = $request->user()->rider->locations()->create([
            'latitude' => $request->validated('latitude'),
            'longitude' => $request->validated('longitude'),
            'recorded_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at,
            ],
        ], JsonResponse::HTTP_CREATED);
    }
}
