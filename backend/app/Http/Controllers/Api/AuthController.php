<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user  = User::create($request->validated());
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'The provided credentials are incorrect.',
            ], 401);
        }

        /** @var User $user */
        $user  = Auth::user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function google(Request $request): JsonResponse
    {
        $request->validate([
            'id_token' => ['required', 'string'],
        ]);

        try {
            $googleUser = Socialite::driver('google')->userFromToken($request->id_token);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Invalid Google token.',
            ], 422);
        }

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            // Link google_id if not yet linked
            if (! $user->google_id) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar'    => $googleUser->getAvatar(),
                ]);
            }
        } else {
            $user = User::create([
                'name'      => $googleUser->getName(),
                'email'     => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'avatar'    => $googleUser->getAvatar(),
                'role'      => 'student',
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(null, 204);
    }
}
