<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'category_id',
        'title',
        'slug',
        'description',
        'price',
        'thumbnail',
        'is_published',
        'offers_certificate',
        'delivery_mode',
        'starts_on',
        'ends_on',
        'total_hours',
    ];

    /** Courses whose lessons are pre-recorded videos. */
    public const DELIVERY_ON_DEMAND = 'on_demand';

    /** Courses delivered as scheduled Meet/Zoom sessions. */
    public const DELIVERY_LIVE = 'live';

    public const DELIVERY_MODES = [
        self::DELIVERY_ON_DEMAND,
        self::DELIVERY_LIVE,
    ];

    protected function casts(): array
    {
        return [
            'price'              => 'decimal:2',
            'is_published'       => 'boolean',
            'offers_certificate' => 'boolean',
            'starts_on'          => 'date',
            'ends_on'            => 'date',
            'total_hours'        => 'decimal:1',
            // Intentionally NOT in $fillable — the push idempotency guard must
            // not be clearable from a request payload. Stamped via forceFill
            // in App\Services\Push\PushDispatcher.
            'push_notified_at'   => 'datetime',
        ];
    }

    /**
     * Whether this course is delivered as scheduled live sessions.
     *
     * The single definition of "live" — every mode-dependent branch (lesson
     * validation, meeting-link exposure, who may mark attendance) reads this
     * instead of comparing the raw column, so the semantics stay in one place.
     */
    public function isLive(): bool
    {
        return $this->delivery_mode === self::DELIVERY_LIVE;
    }

    // Relationships

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, Section::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot('price_paid')
            ->withTimestamps();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }
}
