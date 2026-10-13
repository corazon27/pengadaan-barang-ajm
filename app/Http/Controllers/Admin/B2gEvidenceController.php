<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\B2gEvidenceSubmission;
use App\Services\B2gEvidenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorized, audited download of private B2G evidence. The access event is
 * recorded via a strict audit write BEFORE any bytes are served; if the audit
 * write fails, no document is returned.
 */
class B2gEvidenceController extends Controller
{
    public function download(Request $request, B2gEvidenceSubmission $submission, B2gEvidenceService $service): StreamedResponse
    {
        $this->authorize('reviewB2g', $submission->user);

        $service->recordAccess($request->user(), $submission);

        if (! Storage::disk($submission->disk)->exists($submission->path)) {
            abort(404);
        }

        return Storage::disk($submission->disk)->download($submission->path, $submission->original_filename);
    }
}
