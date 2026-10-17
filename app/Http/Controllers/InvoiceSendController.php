<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoice;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;

class InvoiceSendController extends Controller
{
    /**
     * Posted from the invoice page; the job emails the PDF to the client.
     */
    public function __invoke(Invoice $invoice): RedirectResponse
    {
        $this->authorize('send', $invoice);

        abort_if(in_array($invoice->status, [InvoiceStatus::Paid, InvoiceStatus::Void], true), 422, 'Paid and void invoices cannot be sent.');
        abort_if(blank($invoice->client->email), 422, 'The client has no email address.');

        SendInvoice::dispatch($invoice);

        if ($invoice->isDraft()) {
            $invoice->markAs(InvoiceStatus::Sent);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', __('Invoice queued for sending to :email.', ['email' => $invoice->client->email]));
    }
}
