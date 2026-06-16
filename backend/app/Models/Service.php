<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'description',
        'price',
        'duration_hours',
        'availability_type',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'price'        => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function images(): HasMany
    {
        return $this->hasMany(ServiceImage::class)->orderBy('sort_order');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Returns the absolute URL of the first image (ordered by sort_order),
     * or null when no images are attached.
     *
     * Handles both relative paths (stored in public disk) and absolute/HTTP
     * URLs that were already passed through (e.g. external CDN links) by
     * detecting the presence of a scheme prefix — same gotcha as avatar URLs.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        $first = $this->images->first();

        if (! $first) {
            return null;
        }

        return $this->resolveImageUrl($first->path);
    }

    /**
     * Returns an ordered array of absolute image URLs.
     *
     * @return array<int, string>
     */
    public function getImagesUrlsAttribute(): array
    {
        return $this->images
            ->map(fn ($img) => $this->resolveImageUrl($img->path))
            ->toArray();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve a path to an absolute URL.
     * If the path already starts with http/https, return it as-is.
     * Otherwise defer to Storage::disk('public')->url().
     *
     * Public so that resources (ServiceDetailResource) can delegate here
     * instead of duplicating the resolution logic.
     */
    public function resolveImageUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
