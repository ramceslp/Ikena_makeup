<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\MyAppointmentResource;
use App\Http\Resources\OrderResource;
use App\Http\Resources\UserResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            ->with('course', 'appointment.service', 'items')
            ->latest()
            ->paginate(15);

        return response()->json(
            OrderResource::collection($orders)->response()->getData(true)
        );
    }

    /**
     * GET /api/profile/appointments?scope=upcoming|past
     *
     * The authenticated user's OWN agenda. The admin list
     * (GET /api/admin/appointments) has always been the only way to read
     * appointments; a customer had no endpoint for their own bookings at all,
     * and /profile/orders is the wrong shape for an agenda — it is a
     * reverse-chronological mix of all three order types, keyed on purchase
     * date rather than appointment date.
     *
     * Sort order follows what each scope is FOR: upcoming ascending (the next
     * appointment is the one you came to check), past descending (the most
     * recent visit first). Paginated 15 like orders() above.
     *
     * Cancelled appointments are included rather than filtered out — a
     * cancellation is exactly the kind of thing a customer opens their agenda
     * to verify, and MyAppointmentResource exposes `status`/`cancelled_at` so
     * the client can render it distinctly.
     */
    public function appointments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['sometimes', 'in:upcoming,past'],
        ]);

        $scope = $validated['scope'] ?? 'upcoming';

        // Bare now() is venue-local: app.timezone is pinned to
        // booking.timezone (America/Guayaquil) — see ConfigTimezoneTest.
        // scheduled_date/scheduled_time are stored as venue-local wall-clock
        // values, so they are directly comparable to these.
        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // An appointment counts as upcoming until it has finished, not until
        // it has started — someone mid-appointment should still see it under
        // "próximas", not filed away as history. scheduled_end_time is the
        // denormalized column the overlap queries already rely on (DM-001).
        $isUpcoming = fn (Builder $q) => $q
            ->whereDate('scheduled_date', '>', $today)
            ->orWhere(fn (Builder $sub) => $sub
                ->whereDate('scheduled_date', '=', $today)
                ->where('scheduled_end_time', '>=', $currentTime));

        $isPast = fn (Builder $q) => $q
            ->whereDate('scheduled_date', '<', $today)
            ->orWhere(fn (Builder $sub) => $sub
                ->whereDate('scheduled_date', '=', $today)
                ->where('scheduled_end_time', '<', $currentTime));

        $appointments = $request->user()
            ->appointments()
            ->with(['service', 'order'])
            ->where($scope === 'upcoming' ? $isUpcoming : $isPast)
            ->orderBy('scheduled_date', $scope === 'upcoming' ? 'asc' : 'desc')
            ->orderBy('scheduled_time', $scope === 'upcoming' ? 'asc' : 'desc')
            ->paginate(15);

        return response()->json(
            MyAppointmentResource::collection($appointments)->response()->getData(true)
        );
    }
}
