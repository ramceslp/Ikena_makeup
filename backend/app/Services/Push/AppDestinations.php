<?php

namespace App\Services\Push;

use Illuminate\Support\Collection;

/**
 * Reads config/push_destinations.php and answers the two questions the rest of
 * the feature has about a deep link: "what can the admin choose?" and "does
 * this path actually open something in the app?".
 *
 * Lives as a service rather than as helper functions on the FormRequest so the
 * admin picker (GET .../destinations) and the validation rule
 * (App\Rules\AppDestination) are driven by ONE reading of the catalogue. Two
 * readings is how a picker ends up offering an option the validator rejects.
 */
class AppDestinations
{
    /**
     * Slug segment charset: RFC 3986 "unreserved" characters, minus a segment
     * that is entirely dots.
     *
     * NOT narrowed to Str::slug's output (lowercase, digits, hyphens), even
     * though every slug currently in the database happens to match that. Slugs
     * are only auto-generated when the admin leaves the field blank — see
     * Admin\PostController::store(), and StorePostRequest, whose rule is
     * `['string', 'max:255', 'unique']` with no pattern at all. A manually
     * entered `Promo_Verano_2026` is a perfectly legal slug today, and the app
     * opens it fine, since `/noticias/:slug` matches any segment.
     *
     * Rejecting it here would have produced a false negative with a message
     * that actively misleads — "la app no tiene ninguna pantalla en
     * /noticias/Promo_Verano_2026" would be untrue, sending the admin to look
     * for a routing problem that does not exist.
     *
     * Still deliberately not "anything but a slash". Excluded, and covered by
     * tests: `%` (so `%2F` cannot smuggle in a separator), and a `.`/`..`
     * segment (path traversal).
     */
    private const SLUG_PATTERN = '(?!\.{1,2}$)[A-Za-z0-9._~-]+';

    /** @return Collection<int, array<string, mixed>> */
    public function all(): Collection
    {
        return collect(config('push_destinations', []));
    }

    /**
     * The picker payload: no model class names leak to the client, which only
     * needs to know whether to render the slug field.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPicker(): array
    {
        return $this->all()
            ->map(fn (array $destination) => [
                'key'           => $destination['key'],
                'label'         => $destination['label'],
                'pattern'       => $destination['pattern'],
                'requires_slug' => $this->requiresSlug($destination),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed>|null */
    public function find(string $key): ?array
    {
        return $this->all()->firstWhere('key', $key);
    }

    /**
     * Builds the path a destination + slug resolve to, or null when the key is
     * unknown or a required slug is missing. Callers treat null as "reject",
     * never as "send without a route" — silently dropping a deep link the admin
     * explicitly chose would be the quieter version of the bug this fixes.
     */
    public function resolve(string $key, ?string $slug): ?string
    {
        $destination = $this->find($key);

        if ($destination === null) {
            return null;
        }

        if (! $this->requiresSlug($destination)) {
            return $destination['pattern'];
        }

        $slug = trim((string) $slug);

        if ($slug === '') {
            return null;
        }

        return str_replace('{slug}', $slug, $destination['pattern']);
    }

    /**
     * Whether a fully-built path matches SOME destination in the catalogue.
     *
     * This is what stands between the admin and a blank screen: it is a
     * whitelist, so a path the app has no route for cannot be stored, let alone
     * delivered. Checked against the pattern only — whether the slug points at
     * an existing record is a separate question, answered by
     * App\Rules\AppDestination, because a nonexistent record produces the app's
     * "no encontrado" state rather than an empty <RouterView>.
     */
    public function matchesAny(string $route): bool
    {
        return $this->all()->contains(
            fn (array $destination) => preg_match($this->toRegex($destination['pattern']), $route) === 1
        );
    }

    /**
     * The destination a stored path came from, so an existing history row can
     * be read back into the picker. Returns null for a path that predates the
     * catalogue (rows written while the field was free text).
     *
     * @return array{destination: array<string, mixed>, slug: string|null}|null
     */
    public function match(string $route): ?array
    {
        foreach ($this->all() as $destination) {
            if (preg_match($this->toRegex($destination['pattern']), $route, $matches) === 1) {
                return [
                    'destination' => $destination,
                    'slug'        => $matches['slug'] ?? null,
                ];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $destination */
    public function requiresSlug(array $destination): bool
    {
        return str_contains($destination['pattern'], '{slug}');
    }

    /**
     * `/cursos/{slug}` → `#^/cursos/(?P<slug>[a-z0-9]+(?:-[a-z0-9]+)*)$#`
     *
     * preg_quote runs BEFORE the placeholder is swapped in, so the literal part
     * of a pattern can never be read as regex syntax.
     */
    private function toRegex(string $pattern): string
    {
        $quoted = preg_quote($pattern, '#');

        $body = str_replace(
            preg_quote('{slug}', '#'),
            '(?P<slug>'.self::SLUG_PATTERN.')',
            $quoted
        );

        return '#^'.$body.'$#';
    }
}
