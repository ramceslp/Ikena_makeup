<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'duration',
        'position',
        'is_free',
        'is_practice',
    ];

    protected function casts(): array
    {
        return [
            'duration'    => 'integer',
            'position'    => 'integer',
            'is_free'     => 'boolean',
            'is_practice' => 'boolean',
        ];
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
