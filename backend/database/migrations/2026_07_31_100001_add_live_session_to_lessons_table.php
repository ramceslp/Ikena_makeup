<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            // Meet / Zoom / Teams join link for a live session. Kept separate
            // from video_url so a live lesson can carry BOTH: the join link
            // before the session and the recording afterwards.
            $table->string('meeting_url')->nullable()->after('video_url');

            // When the live session happens. Combined with duration (seconds)
            // it defines the window in which meeting_url is served.
            $table->dateTime('starts_at')->nullable()->after('meeting_url');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['meeting_url', 'starts_at']);
        });
    }
};
