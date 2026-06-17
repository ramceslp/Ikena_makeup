<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * POST /api/profile
     *
     * Update the authenticated user's profile (name, email, avatar).
     * Using POST instead of PATCH/PUT because PHP does not parse multipart
     * (file upload) bodies for PUT or PATCH requests.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if ($request->hasFile('avatar')) {
            // Delete the old avatar only if it is a stored path, not a remote http URL
            if ($user->avatar && ! str_starts_with($user->avatar, 'http')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        return response()->json(['data' => new UserResource($user)]);
    }

    /**
     * PUT /api/profile/password
     *
     * Change the authenticated user's password.
     * Google-only accounts (password = null) cannot use this endpoint.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        // Google-login users have no password set
        if (is_null($user->password)) {
            return response()->json([
                'message' => 'Tu cuenta inicia sesión con Google y no tiene contraseña.',
            ], 422);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        // The 'hashed' cast on User::$casts handles bcrypt automatically
        $user->update(['password' => $validated['password']]);

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    /**
     * GET /api/profile/orders
     *
     * Return ALL of the authenticated user's orders (any status),
     * latest first, with course info, paginated.
     */
    public function orders(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with('course', 'appointment.service')
            ->latest()
            ->paginate(15);

        return response()->json(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }
}
