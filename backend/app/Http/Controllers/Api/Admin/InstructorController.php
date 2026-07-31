<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Read-only list of users who can own a course.
 *
 * Exists to populate the instructor selector in the admin course form. Returns
 * only id and name — a picker has no business carrying emails around.
 */
class InstructorController extends Controller
{
    /**
     * GET /api/admin/instructors
     */
    public function index(): JsonResponse
    {
        $instructors = User::query()
            ->whereIn('role', ['instructor', 'admin'])
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        return response()->json([
            'data' => $instructors->map(fn (User $user) => [
                'id'   => $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ])->values(),
        ]);
    }
}
