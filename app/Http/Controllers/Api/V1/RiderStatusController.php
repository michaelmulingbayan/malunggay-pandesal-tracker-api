<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateRiderStatusRequest;
use App\Http\Resources\RiderResource;
use App\Models\Rider;
use App\UserStatus;

class RiderStatusController extends Controller
{
    /**
     * Activate or deactivate a rider's account.
     *
     * Deactivating also revokes all of the rider's API tokens, signing them out on every device.
     */
    public function update(UpdateRiderStatusRequest $request, Rider $rider): RiderResource
    {
        $isActive = $request->boolean('is_active');

        $rider->user->update([
            'status' => $isActive ? UserStatus::Active : UserStatus::Inactive,
        ]);

        if (! $isActive) {
            $rider->user->tokens()->delete();
        }

        return new RiderResource($rider->load('user'));
    }
}
