<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DestroyDeviceTokenRequest;
use App\Http\Requests\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

/**
 * DeviceTokenController — register/unregister FCM device tokens
 * (mobile-capacitor-setup PR3, design Decision 2:
 * sdd/mobile-capacitor-setup/design.md).
 *
 * store()   (auth:sanctum) idempotent upsert on `token` — re-registering the
 *           same token (e.g. app reinstall on a shared device, or a token
 *           the FCM SDK re-issues) reassigns it to the current user and
 *           refreshes `last_used_at` instead of creating a duplicate row.
 * destroy() (auth:sanctum) removes the token, scoped to the authenticated
 *           user's own rows — a token guess/leak cannot be used to delete
 *           another user's registration.
 */
class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $validated = $request->validated();

        DeviceToken::updateOrCreate(
            ['token' => $validated['token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'last_used_at' => now(),
            ],
        );

        return response()->json(['data' => ['status' => 'registered']]);
    }

    public function destroy(DestroyDeviceTokenRequest $request): JsonResponse
    {
        DeviceToken::where('token', $request->validated()['token'])
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['data' => ['status' => 'removed']]);
    }
}
