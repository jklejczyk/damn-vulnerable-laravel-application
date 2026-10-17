<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

/**
 * Renders an invoice with dompdf/dompdf 2.0.0, which is vulnerable on purpose (DVLA-015).
 * The template only prints escaped fields and the logo is embedded as a data URI, so no
 * remote resource or user-supplied HTML reaches dompdf.
 */
class InvoicePdf
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing('company', 'client', 'items');

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.invoice', [
            'invoice' => $invoice,
            'logo' => $this->logo($invoice),
        ])->render());
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function filename(Invoice $invoice): string
    {
        return str_replace('/', '-', $invoice->number).'.pdf';
    }

    private function logo(Invoice $invoice): ?string
    {
        $path = $invoice->company->logo_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path);

        return in_array($mime, ['image/png', 'image/jpeg'], true)
            ? 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path))
            : null;
    }
}
