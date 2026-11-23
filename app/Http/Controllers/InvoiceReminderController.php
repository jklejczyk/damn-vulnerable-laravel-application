<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Jobs\SendInvoice;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class InvoiceReminderController extends Controller
{
    /**
     * DVLA-SAFE-007: registered outside the auth group, so the action checks the login itself
     * before the policy.
     */
    public function __invoke(Invoice $invoice): RedirectResponse
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $this->authorize('send', $invoice);

        abort_unless($invoice->status === InvoiceStatus::Sent, 422, 'Only sent invoices can be reminded.');
        abort_if(blank($invoice->client->email), 422, 'The client has no email address.');

        SendInvoice::dispatch($invoice);

        return redirect()->route('invoices.show', $invoice)->with('status', __('Reminder queued for :email.', ['email' => $invoice->client->email]));
    }
}
