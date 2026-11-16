<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Authenticate the user and issue a Sanctum API token for the device.
     *
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        if ($user->status !== UserStatus::Active) {
            return response()->json([
                'message' => 'Your account is inactive. Please contact an administrator.',
            ], Response::HTTP_FORBIDDEN);
        }

        $token = $user->createToken(
            name: $request->validated('device_name'),
            abilities: [$user->role->value],
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('rider')),
        ]);
    }

    /**
     * Get the currently authenticated user.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('rider'));
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
