<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine if the authenticated instructor owns this course.
     * Returns false (→ 403) when the user is not the course owner.
     */
    public function manage(User $user, Course $course): bool
    {
        return $user->id === $course->instructor_id;
    }
}
