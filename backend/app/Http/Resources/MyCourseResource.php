<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MyCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $totalLessons     = $this->resource->total_lessons ?? 0;
        $completedLessons = $this->resource->completed_lessons ?? 0;
        $percentage       = $totalLessons > 0
            ? (int) round($completedLessons / $totalLessons * 100)
            : 0;

        return [
            'id'                  => $this->id,
            'title'               => $this->title,
            'slug'                => $this->slug,
            'thumbnail'           => $this->thumbnail,
            'instructor'          => [
                'id'   => $this->instructor->id,
                'name' => $this->instructor->name,
            ],
            'total_lessons'       => $totalLessons,
            'completed_lessons'   => $completedLessons,
            'progress_percentage' => $percentage,
            // Absolute URL of the web lesson player for this course.
            //
            // Served by the API rather than assembled client-side on purpose:
            // the mobile app has no lesson player of its own and opens this in
            // the system browser (@capacitor/browser), but it only knows
            // VITE_API_URL — the web origin is a separate host in production
            // and a separate port in development. config('app.frontend_url')
            // is already the single source of truth for it (see
            // CheckoutHandoffController::store), so deriving the link here
            // avoids a second, drift-prone copy of that origin in the app's
            // build config.
            'web_url'             => rtrim((string) config('app.frontend_url'), '/')."/learn/{$this->slug}",
        ];
    }
}
