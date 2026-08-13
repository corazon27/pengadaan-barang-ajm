<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Document;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Rfq;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(private readonly PdfService $pdfService) {}

    /**
     * Stream the Surat Penawaran Harga (quotation) PDF for an RFQ.
     */
    public function quotationPdf(Rfq $rfq): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $rfq);

        return $this->stream($rfq->quotation_pdf_url, 'Surat-Penawaran-Harga-'.$rfq->rfq_number);
    }

    /**
     * Stream the BAST draft PDF for an order.
     */
    public function bastPdf(Order $order): BinaryFileResponse|JsonResponse
    {
        $bast = $order->bastDocument()->first();

        if (! $bast) {
            return $this->missing();
        }

        $this->authorize('view', $bast);

        return $this->stream($bast->bast_document_url, 'BAST-'.$bast->bast_number);
    }

    /**
     * Stream the invoice PDF for an invoice.
     */
    public function invoicePdf(Invoice $invoice): BinaryFileResponse|JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->stream($invoice->invoice_pdf_url, 'Invoice-'.$invoice->invoice_number);
    }

    /**
     * Stream a stored document from the private documents disk.
     */
    private function stream(?string $path, string $filename): BinaryFileResponse|JsonResponse
    {
        if (! $path || ! Storage::disk('documents')->exists($path)) {
            return $this->missing();
        }

        return response()->file(
            Storage::disk('documents')->path($path),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->pdfService->sanitizeFilename($filename).'.pdf"',
            ]
        );
    }

    private function missing(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Dokumen belum tersedia.',
            'data' => null,
            'errors' => null,
        ], 404);
    }
}
