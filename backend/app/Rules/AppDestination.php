<?php

namespace App\Rules;

use App\Services\Push\AppDestinations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a push deep link opens something real in the mobile app.
 *
 * Two failure modes, deliberately distinguished because they FEEL different to
 * a user who taps the notification:
 *
 *  1. The path matches no route in the app at all (`/courses/x`, copied from the
 *     web panel, where the app's route is `/cursos/x`). vue-router resolves it
 *     without complaint and renders nothing — a blank screen, with no error on
 *     the device, in the logs, or in the admin history. This rule is the only
 *     place that can catch it, because by delivery time nothing is watching.
 *
 *  2. The path is a real route but the slug points at no published record. The
 *     app handles this correctly already (its detail views render a "no
 *     encontrado" state), so it is not a blank screen — but it is still a
 *     broadcast to every device advertising a page that isn't there, and it is
 *     cheap to catch here with one indexed lookup.
 *
 * Replaces the previous `regex:/^\/(?!\/)/`, which only established that the
 * string was an internal path, not that it led anywhere. That check remains
 * implied: every pattern in the catalogue starts with a single slash.
 */
class AppDestination implements ValidationRule
{
    public function __construct(private AppDestinations $destinations) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('El destino de la notificación no es válido.');

            return;
        }

        $match = $this->destinations->match($value);

        if ($match === null) {
            $fail(
                'La app no tiene ninguna pantalla en "'.$value.'". '
                .'Elegí un destino de la lista — ojo que las rutas del panel web y las de la app '
                .'no son iguales (un curso es /courses/… en la web y /cursos/… en la app).'
            );

            return;
        }

        $model = $match['destination']['model'];
        $slug  = $match['slug'];

        if ($model === null || $slug === null) {
            return;
        }

        $exists = $model::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->exists();

        if (! $exists) {
            $fail(
                'No hay ningún contenido publicado con el slug "'.$slug.'" para el destino '
                .'"'.$match['destination']['label'].'". La notificación abriría una pantalla vacía.'
            );
        }
    }
}
