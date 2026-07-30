<?php

/**
 * The destinations a push notification's deep link is allowed to open in the
 * MOBILE APP (docs/push-notifications/HANDOFF.md §5.6).
 *
 * This is the server-side mirror of App/src/router/index.js. It exists because
 * `data.route` is handed straight to the app's vue-router, and vue-router 4
 * does NOT reject a push() to an unmatched path — it resolves with an empty
 * `matched` array, so the app renders its layout chrome around an empty
 * <RouterView> and the admin gets no error anywhere. A typo, or a path copied
 * from the WEB panel, used to produce exactly that blank screen.
 *
 * ⚠️ The web panel and the app do NOT share a route vocabulary. A course detail
 * is `/courses/{slug}` on the web and `/cursos/{slug}` in the app. Copying a URL
 * out of the browser's address bar is therefore the single most likely way to
 * aim a notification at nowhere — which is why the admin UI offers a picker
 * built from this list instead of a free-text field.
 *
 * Keeping this in sync with the real router is enforced, not trusted:
 * tests/Feature/Push/AppDestinationsSyncTest.php reads App/src/router/index.js
 * and fails if the two ever drift. Adding a route to the app without listing it
 * here only costs an option in the picker; listing one HERE that the app does
 * not have is what reaches a user as a dead notification, so that is the
 * direction the test enforces.
 *
 * Each entry:
 *   key      stable identifier the admin UI submits
 *   label    Spanish copy shown in the picker
 *   pattern  app path, with `{slug}` standing in for a required slug segment
 *   model    null, or the model class whose slug must exist for this pattern
 *
 * `/login` is deliberately absent: it is the app's only chrome-less route and
 * exists to be redirected TO, never to be advertised as a destination.
 * `/profile` IS listed even though it is auth-gated — the app's guard sends a
 * logged-out user to /login with a redirect back, which is a working landing.
 */

use App\Models\Course;
use App\Models\Post;
use App\Models\Product;
use App\Models\Service;

return [

    ['key' => 'home',           'label' => 'Inicio',                'pattern' => '/',                'model' => null],
    ['key' => 'news',           'label' => 'Noticias',              'pattern' => '/noticias',        'model' => null],
    ['key' => 'news-detail',    'label' => 'Una noticia concreta',  'pattern' => '/noticias/{slug}', 'model' => Post::class],
    ['key' => 'courses',        'label' => 'Cursos',                'pattern' => '/cursos',          'model' => null],
    ['key' => 'course-detail',  'label' => 'Un curso concreto',     'pattern' => '/cursos/{slug}',   'model' => Course::class],
    ['key' => 'products',       'label' => 'Productos',             'pattern' => '/products',        'model' => null],
    ['key' => 'product-detail', 'label' => 'Un producto concreto',  'pattern' => '/products/{slug}', 'model' => Product::class],
    ['key' => 'services',       'label' => 'Servicios',             'pattern' => '/services',        'model' => null],
    ['key' => 'service-detail', 'label' => 'Un servicio concreto',  'pattern' => '/services/{slug}', 'model' => Service::class],
    ['key' => 'cart',           'label' => 'Carrito',               'pattern' => '/cart',            'model' => null],
    ['key' => 'profile',        'label' => 'Mi perfil',             'pattern' => '/profile',         'model' => null],

];
