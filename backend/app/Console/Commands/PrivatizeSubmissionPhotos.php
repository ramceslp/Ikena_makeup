<?php

namespace App\Console\Commands;

use App\Models\PracticeSubmission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * One-shot migration for practice photos already on disk.
 *
 * Practice submissions used to be written to the 'public' disk, which is
 * symlinked from public/storage — every student's before/after face photo was
 * readable by anyone holding the URL. New uploads now go to the private
 * 'local' disk, but rows created before that change still point at files
 * sitting in the world-readable tree; moving the file is what actually closes
 * the exposure.
 *
 * The stored before_path/after_path are relative and identical on both disks,
 * so no database update is needed — only the bytes move.
 *
 * Safe to re-run: a variant already migrated (present on 'local') is skipped,
 * and the public copy is only deleted once the private copy is confirmed
 * written.
 */
class PrivatizeSubmissionPhotos extends Command
{
    protected $signature = 'submissions:privatize {--dry-run : Report what would move without touching any file}';

    protected $description = 'Move practice-submission photos off the public disk onto the private disk';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $public  = Storage::disk('public');
        $private = Storage::disk('local');

        $moved = $skipped = $missing = 0;

        foreach (PracticeSubmission::cursor() as $submission) {
            foreach (PracticeSubmission::VARIANTS as $variant) {
                $path = $submission->pathForVariant($variant);

                if (blank($path)) {
                    continue;
                }

                if ($private->exists($path)) {
                    // Already migrated on an earlier run.
                    $skipped++;

                    // A public copy left behind is still exposed, so clear it.
                    if ($public->exists($path) && ! $dryRun) {
                        $public->delete($path);
                    }

                    continue;
                }

                if (! $public->exists($path)) {
                    $this->warn("Missing on both disks: {$path} (submission {$submission->id})");
                    $missing++;

                    continue;
                }

                if ($dryRun) {
                    $this->line("Would move: {$path}");
                    $moved++;

                    continue;
                }

                // Write the private copy FIRST and confirm it, so an
                // interrupted run can never destroy the only copy.
                $private->put($path, $public->get($path));

                if (! $private->exists($path)) {
                    $this->error("Failed to write private copy: {$path} — public copy left in place.");

                    continue;
                }

                $public->delete($path);
                $moved++;
            }
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Moved: {$moved}  Already private: {$skipped}  Missing: {$missing}");

        if ($missing > 0) {
            $this->warn('Rows with missing files were left untouched — their URLs will 404.');
        }

        return self::SUCCESS;
    }
}
