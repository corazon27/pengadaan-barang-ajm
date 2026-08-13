<?php

declare(strict_types=1);

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    /**
     * Render a Blade view into a PDF and persist it on the private
     * "documents" disk. Returns the stored relative path, or an empty string
     * when rendering fails (the failure is logged and surfaced as 404 by the
     * caller).
     */
    public function generate(string $view, array $data, string $directory, string $filename): string
    {
        try {
            $pdf = Pdf::loadView($view, $data);

            $relativePath = trim($directory, '/').'/'.$filename;

            Storage::disk('documents')->put($relativePath, $pdf->output());

            return $relativePath;
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    /**
     * Sanitize a string for safe use inside a Content-Disposition filename.
     */
    public function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^\p{L}\p{N}\w\-. ]+/u', '', $filename);
        $filename = preg_replace('/\s+/', '-', (string) $filename);

        return trim((string) $filename, '-') ?: 'document';
    }
}
