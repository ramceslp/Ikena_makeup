<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `push_notified_at` to `posts` and `courses` — the idempotency guard for
 * the automatic push triggers (push-notifications Slice 1:
 * sdd/push-notifications/HANDOFF.md §5.2).
 *
 * A trigger fires only when this column is NULL, and stamps it on dispatch.
 * Without it the "new post" push would re-send every time an admin corrects a
 * typo in an already-published post, and the "new course" push would re-send
 * on every unpublish/republish cycle.
 *
 * Why a dedicated column instead of deriving it from `push_notification_logs`:
 * a log row records a *send attempt* (including 'skipped' and 'failed' ones)
 * and is a reporting surface that could reasonably be pruned; the trigger guard
 * is a business invariant on the post/course itself and must not depend on
 * retention of an audit table. Kept nullable with no default so every existing
 * row is treated as "never notified" — see the note below.
 *
 * NOTE for the Firebase enablement step (HANDOFF §8): every post and course
 * that exists today backfills as NULL, so the FIRST publish-toggle on any old
 * record after push goes live would notify. Pre-existing published rows are not
 * at risk (the triggers only run on a publish transition, not on read), but if
 * a bulk re-publish is ever performed, stamp `push_notified_at = now()` on the
 * already-public rows first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('push_notified_at')->nullable()->after('published_at');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->timestamp('push_notified_at')->nullable()->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('push_notified_at');
        });

        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('push_notified_at');
        });
    }
};
