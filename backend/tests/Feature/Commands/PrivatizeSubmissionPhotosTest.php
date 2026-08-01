<?php

namespace Tests\Feature\Commands;

use App\Models\PracticeSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PrivatizeSubmissionPhotosTest — the one-shot migration that moves practice
 * photos already on disk off the world-readable public tree.
 *
 * Changing where NEW uploads land does nothing for rows created before that
 * change: their files are still sitting under public/storage, readable by
 * anyone with the URL. Moving the bytes is what actually closes the exposure,
 * which makes this command the load-bearing half of the fix — and it runs
 * against real student photos, so it must never lose one.
 */
class PrivatizeSubmissionPhotosTest extends TestCase
{
    use RefreshDatabase;

    private function submissionWithPublicFiles(): PracticeSubmission
    {
        $submission = PracticeSubmission::factory()->create();

        Storage::disk('public')->put($submission->before_path, 'before-bytes');
        Storage::disk('public')->put($submission->after_path, 'after-bytes');

        return $submission;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_it_moves_photos_to_the_private_disk(): void
    {
        $submission = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize')->assertSuccessful();

        Storage::disk('local')->assertExists($submission->before_path);
        Storage::disk('local')->assertExists($submission->after_path);
    }

    public function test_it_removes_the_public_copies(): void
    {
        $submission = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize')->assertSuccessful();

        Storage::disk('public')->assertMissing($submission->before_path);
        Storage::disk('public')->assertMissing($submission->after_path);
    }

    public function test_it_preserves_the_file_contents(): void
    {
        $submission = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize')->assertSuccessful();

        $this->assertSame('before-bytes', Storage::disk('local')->get($submission->before_path));
        $this->assertSame('after-bytes', Storage::disk('local')->get($submission->after_path));
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $submission = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize')->assertSuccessful();
        $this->artisan('submissions:privatize')->assertSuccessful();

        $this->assertSame('before-bytes', Storage::disk('local')->get($submission->before_path));
        Storage::disk('public')->assertMissing($submission->before_path);
    }

    public function test_dry_run_moves_nothing(): void
    {
        $submission = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('public')->assertExists($submission->before_path);
        Storage::disk('local')->assertMissing($submission->before_path);
    }

    public function test_it_clears_a_public_copy_left_beside_an_already_migrated_file(): void
    {
        // A half-finished earlier run, or a restore from an old backup: the
        // private copy exists but the exposed public one is still there.
        $submission = $this->submissionWithPublicFiles();
        Storage::disk('local')->put($submission->before_path, 'before-bytes');

        $this->artisan('submissions:privatize')->assertSuccessful();

        Storage::disk('public')->assertMissing($submission->before_path);
        Storage::disk('local')->assertExists($submission->before_path);
    }

    public function test_a_row_whose_file_is_missing_everywhere_does_not_fail_the_run(): void
    {
        $orphan = PracticeSubmission::factory()->create();
        $intact = $this->submissionWithPublicFiles();

        $this->artisan('submissions:privatize')->assertSuccessful();

        // The orphan is reported, and the healthy row still migrates.
        Storage::disk('local')->assertMissing($orphan->before_path);
        Storage::disk('local')->assertExists($intact->before_path);
    }
}
