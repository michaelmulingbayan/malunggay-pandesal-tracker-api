<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRiderRequest;
use App\Http\Resources\RiderResource;
use App\Models\Rider;
use App\Models\User;
use App\UserRole;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RiderController extends Controller
{
    /**
     * List all riders, alphabetically by name.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Rider::class);

        $riders = Rider::query()
            ->with('user')
            ->orderBy(User::select('name')->whereColumn('users.id', 'riders.user_id'))
            ->get();

        return RiderResource::collection($riders);
    }

    /**
     * Register a rider: creates their login account and rider profile together.
     */
    public function store(StoreRiderRequest $request): JsonResponse
    {
        $rider = DB::transaction(function () use ($request): Rider {
            $user = User::create([
                ...$request->safe()->only(['name', 'email', 'password']),
                'role' => UserRole::Rider,
                'status' => UserStatus::Active,
            ]);

            return $user->rider()->create($request->safe()->only(['phone_number', 'address']));
        });

        return (new RiderResource($rider->load('user')))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
