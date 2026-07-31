<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine if the user may author this course's content.
     *
     * Two distinct grounds grant authoring rights:
     *   - Ownership: the instructor who owns the course.
     *   - Administration: an admin governs the whole catalog, so admins may
     *     author any course. This is what lets the admin catalog view hand off
     *     deep authoring (sections/lessons) to the existing instructor editor
     *     instead of duplicating it.
     *
     * This is about AUTHORING one course, not about listing. The instructor
     * index stays scoped to instructor_id so "Panel instructor" keeps meaning
     * "my courses" even for an admin; catalog-wide listing lives in the admin
     * course endpoints.
     */
    public function manage(User $user, Course $course): bool
    {
        return $user->isAdmin() || $user->id === $course->instructor_id;
    }
}
