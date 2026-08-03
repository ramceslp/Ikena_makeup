<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `visitor_events` — an append-only, identity-free navigation event
 * log (visitor-analytics PR1, design D2/D3: sdd/visitor-analytics/design).
 *
 * No visitor identity of any kind is stored (design D1): no cookie, no
 * client-side storage, no IP/user-agent hash, no session column. `user_id`
 * is the only identity column, populated only when the request already
 * carries a bearer token — never derived or inferred.
 *
 * `entity_id` deliberately carries no foreign key. A `constrained()` /
 * `cascadeOnDelete()` FK would erase view history the instant a product,
 * service, course, or post is deleted, silently rewriting past rankings and
 * funnels. Events must outlive their subject for the full 13-month
 * retention window.
 *
 * No `timestamps()`. `created_at` would be provably identical to
 * `occurred_at` on every row (both are `now()` inside the same synchronous
 * request), and `updated_at` is meaningless on a table with no update path.
 *
 * Indexes:
 *  - `(occurred_at)` serves every reporting query's [from, to) bound and
 *    the retention prune's `WHERE occurred_at < ?`. It is monotonically
 *    increasing, so inserts append at the B-tree tail.
 *  - `(event_type, entity_type, entity_id, occurred_at)` serves the entity
 *    legs (rankings per type, funnel view/cart stages grouped by entity).
 *  - `is_bot` is deliberately absent from both indexes. It will be ~99%
 *    `false`, so as an index column it eliminates nothing and only widens
 *    the index it belongs to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitor_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 20); // page_view | add_to_cart
            $table->string('path', 255);
            $table->string('route_name', 64)->nullable();
            $table->string('entity_type', 20)->nullable(); // product | service | course | post
            $table->unsignedBigInteger('entity_id')->nullable(); // resolved server-side; no FK
            $table->string('referrer_group', 16); // instagram | google | direct | other
            $table->boolean('is_bot')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');

            $table->index('occurred_at');
            $table->index(
                ['event_type', 'entity_type', 'entity_id', 'occurred_at'],
                'visitor_events_entity_leg_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_events');
    }
};
