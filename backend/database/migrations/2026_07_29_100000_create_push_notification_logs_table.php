<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `push_notification_logs` — one row per push broadcast, covering BOTH
 * automatic triggers (a news post published, a course made available) and
 * custom sends composed by an admin (push-notifications Slice 1, decision D3:
 * docs/push-notifications/HANDOFF.md).
 *
 * A single unified timeline — rather than an admin-only history — is what lets
 * the panel answer "was the course-published push actually sent?", which is the
 * question that actually gets asked when someone reports not receiving one.
 *
 * `type` and `status` are plain strings (not native DB enums), matching the
 * existing `checkout_handoffs.type` and `device_tokens.platform` convention in
 * this project for SQLite/MySQL portability; the allowed values are constrained
 * at the validation and model layer instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_logs', function (Blueprint $table) {
            $table->id();

            // 'post.published' | 'course.published' | 'custom'
            $table->string('type', 40);

            $table->string('title');
            $table->text('body');

            // Deep-link payload delivered to the app as FCM `data`,
            // e.g. { "route": "/noticias/mi-noticia" }. Nullable because a
            // custom broadcast does not have to link anywhere.
            $table->json('data')->nullable();

            // 'all' for now. Present from day one as the seam for future
            // segments (enrolled students, past buyers) without a migration.
            $table->string('audience', 40)->default('all');

            // The admin who composed a custom send; NULL for automatic
            // triggers. nullOnDelete so deleting an admin preserves history.
            $table->foreignId('sent_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Device tokens targeted, and the per-token outcome reported back
            // by FCM (see App\Services\Push\PushBroadcaster).
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failure_count')->default(0);

            // 'pending' | 'sent' | 'failed' | 'skipped'
            // 'skipped' = config('push.enabled') was false at dispatch time.
            $table->string('status', 20)->default('pending');

            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // The admin history is always read newest-first, unfiltered or
            // filtered by type.
            $table->index(['created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_logs');
    }
};
