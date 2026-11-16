<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockReturnResource;
use App\Models\StockReturn;
use App\StockReturnStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StockReturnVerificationController extends Controller
{
    /**
     * Verify a pending stock return (the admin confirms the pieces were physically received).
     *
     * @throws ValidationException
     */
    public function store(Request $request, StockReturn $stockReturn): StockReturnResource
    {
        Gate::authorize('verify', $stockReturn);

        if ($stockReturn->status !== StockReturnStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => ['Only pending returns can be verified.'],
            ]);
        }

        $stockReturn->update([
            'status' => StockReturnStatus::Verified,
            'verified_by' => $request->user()->id,
        ]);

        return new StockReturnResource($stockReturn->load(['stockDistribution.rider.user', 'verifier']));
    }
}
