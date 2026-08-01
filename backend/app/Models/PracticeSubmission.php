<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

class PracticeSubmission extends Model
{
    use HasFactory;

    /**
     * How long a minted photo URL stays valid.
     *
     * Long enough that an open review modal keeps working through a grading
     * session, short enough that a forwarded link dies quickly. A stale URL
     * recovers on page refresh, since the JSON response mints fresh ones.
     */
    public const URL_TTL_MINUTES = 30;

    /** The two photo variants a submission carries. */
    public const VARIANTS = ['before', 'after'];

    protected $fillable = [
        'lesson_id',
        'user_id',
        'before_path',
        'after_path',
        'status',
        'feedback',
        'graded_by',
        'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'graded_at' => 'datetime',
        ];
    }

    // Relationships

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // Accessors

    /**
     * These are photographs of a student's face, so they live on the private
     * disk and are reached through a temporary signed route instead of a
     * permanent public /storage URL.
     *
     * Signed rather than auth:sanctum because both clients render them in
     * <img :src="..."> and an <img> tag cannot send an Authorization header —
     * this API has no cookie session to fall back on. Authorisation therefore
     * happens where the URL is MINTED: the endpoints that serialise this model
     * are already ownership- and role-gated. See
     * tests/Feature/PracticeSubmissionPrivacyTest.php.
     */
    public function getBeforeUrlAttribute(): string
    {
        return $this->signedPhotoUrl('before');
    }

    public function getAfterUrlAttribute(): string
    {
        return $this->signedPhotoUrl('after');
    }

    private function signedPhotoUrl(string $variant): string
    {
        return URL::temporarySignedRoute(
            'submissions.file',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['submission' => $this->id, 'variant' => $variant],
        );
    }

    /**
     * Storage path for one variant, or null when the variant is unknown.
     */
    public function pathForVariant(string $variant): ?string
    {
        return match ($variant) {
            'before' => $this->before_path,
            'after'  => $this->after_path,
            default  => null,
        };
    }
}
