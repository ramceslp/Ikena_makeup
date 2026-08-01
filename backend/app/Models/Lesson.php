<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_id',
        'title',
        'description',
        'video_url',
        'meeting_url',
        'starts_at',
        'duration',
        'position',
        'is_free',
        'is_practice',
    ];

    /** Minutes before starts_at at which the meeting link becomes readable. */
    public const MEETING_LEAD_MINUTES = 15;

    /** Window length assumed when a live lesson has no duration set. */
    public const MEETING_FALLBACK_MINUTES = 180;

    protected function casts(): array
    {
        return [
            'starts_at'   => 'datetime',
            'duration'    => 'integer',
            'position'    => 'integer',
            'is_free'     => 'boolean',
            'is_practice' => 'boolean',
        ];
    }

    /**
     * Moment the join link becomes readable — MEETING_LEAD_MINUTES before the
     * session starts. Null when the lesson has no scheduled session at all.
     */
    public function meetingAvailableAt(): ?Carbon
    {
        return $this->starts_at?->copy()->subMinutes(self::MEETING_LEAD_MINUTES);
    }

    /**
     * Whether the join link may be served to an enrolled student right now.
     *
     * Closes at starts_at + duration so a link does not stay readable forever
     * after the session ends. duration is stored in SECONDS; when it is null
     * we fall back to a fixed window rather than leaving the link open.
     */
    public function meetingWindowIsOpen(): bool
    {
        if ($this->starts_at === null || $this->meeting_url === null) {
            return false;
        }

        $closesAt = $this->duration
            ? $this->starts_at->copy()->addSeconds($this->duration)
            : $this->starts_at->copy()->addMinutes(self::MEETING_FALLBACK_MINUTES);

        return now()->between($this->meetingAvailableAt(), $closesAt);
    }

    // Relationships

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function completedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lesson_progress')
            ->withPivot('completed_at')
            ->withTimestamps();
    }

    /**
     * Get the course this lesson belongs to (via section).
     */
    public function getCourseAttribute(): ?Course
    {
        return $this->section?->course;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(PracticeSubmission::class);
    }
}
