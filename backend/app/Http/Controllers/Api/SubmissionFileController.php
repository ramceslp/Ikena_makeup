<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PracticeSubmission;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SubmissionFileController — serves practice-submission photos from the
 * private disk.
 *
 * Reached only through a temporary signed URL minted by
 * PracticeSubmission::getBeforeUrlAttribute()/getAfterUrlAttribute(). The
 * 'signed' middleware on the route is the access control: the URL is
 * HMAC-bound to this submission id and variant and expires after
 * PracticeSubmission::URL_TTL_MINUTES, and it is only ever handed to a caller
 * that already passed the ownership/role gate on the endpoint that serialised
 * the submission.
 *
 * There is deliberately NO auth:sanctum here — both clients render these in
 * <img :src="submission.before_url">, and an <img> tag cannot send an
 * Authorization header (this API has no cookie session: supports_credentials
 * is false). The accepted, documented residual is that someone already
 * authorised to view a photo can forward a working link until it expires.
 */
class SubmissionFileController extends Controller
{
    /**
     * GET /api/submissions/{submission}/{variant}
     */
    public function show(PracticeSubmission $submission, string $variant): StreamedResponse
    {
        // The route already constrains {variant}; this keeps the controller
        // correct on its own terms rather than relying on that alone.
        $path = $submission->pathForVariant($variant);

        abort_if($path === null, 404);

        // A row whose file is gone is a 404, not a 500 — deleting the file out
        // from under the row (a bad restore, a manual cleanup) should not read
        // as a server fault.
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
