<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoicePdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice, InvoicePdf $pdf): Response
    {
        $this->authorize('view', $invoice);

        return response($pdf->render($invoice), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$pdf->filename($invoice).'"',
        ]);
    }
}
