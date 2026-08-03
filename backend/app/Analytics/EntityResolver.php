<?php

namespace App\Analytics;

use App\Models\Course;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;

/**
 * EntityResolver — resolves (entity_type, slug) to the entity's numeric
 * primary key, server-side (visitor-analytics PR1b, design D6:
 * sdd/visitor-analytics/design).
 *
 * The id is the funnel's join key against orders/appointments in a later
 * slice, and a slug is not stable across renames — resolving it
 * server-side (never trusting a client-supplied id) keeps entity_id a
 * genuine foreign-key value from the same table the funnel later joins
 * against, rather than whatever number the client asserted.
 *
 * An unknown slug or an unknown entity type resolves to null; the caller
 * (VisitorEventController) still records the pageview against its
 * path/route_name — a resolution miss is never a reason to discard the
 * event.
 */
class EntityResolver
{
    private const MODEL_MAP = [
        'product' => Product::class,
        'service' => Service::class,
        'course' => Course::class,
        'post' => Post::class,
    ];

    public function resolve(?string $entityType, ?string $slug): ?int
    {
        if ($entityType === null || $slug === null || trim($slug) === '') {
            return null;
        }

        $model = self::MODEL_MAP[$entityType] ?? null;

        if ($model === null) {
            return null;
        }

        /** @var int|null $id */
        $id = $model::query()->where('slug', $slug)->value('id');

        return $id;
    }
}
