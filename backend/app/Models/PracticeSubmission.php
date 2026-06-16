<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PracticeSubmission extends Model
{
    use HasFactory;

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

    public function getBeforeUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->before_path);
    }

    public function getAfterUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->after_path);
    }
}
