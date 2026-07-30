<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Push notifications kill switch
     |--------------------------------------------------------------------------
     |
     | Mirrors the mobile app's build-time `VITE_PUSH_ENABLED` flag
     | (App/src/config/env.js) on the server side, and exists for the same
     | reason: push must fail SAFE when Firebase is not configured.
     |
     | `kreait` throws the first time the Messaging client is resolved if no
     | credentials can be discovered (see config/firebase.php — it looks at
     | FIREBASE_CREDENTIALS, then GOOGLE_APPLICATION_CREDENTIALS, then GCE
     | metadata). Without this flag, publishing a post or a course would throw
     | in local development and in CI, where no service account exists.
     |
     | Default is FALSE — an unset PUSH_ENABLED must never enable the feature.
     | When disabled, App\Services\Push\PushDispatcher still records the send
     | attempt in `push_notification_logs` with status 'skipped', so the admin
     | history shows what *would* have gone out, rather than silently
     | swallowing the event.
     |
     | Turning this on requires the manual Firebase setup documented in
     | docs/push-notifications/HANDOFF.md §8.
     |
     */

    'enabled' => (bool) env('PUSH_ENABLED', false),
];
