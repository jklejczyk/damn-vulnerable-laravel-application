<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * DVLA-SAFE-001 and DVLA-SAFE-002: the payment gateway calls this without a session, so the
 * route needs no login and is rightly excluded from CSRF; the HMAC signature authenticates it.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.payments.webhook_secret');
        $signature = (string) $request->header('X-Signature');

        // Without a configured secret nothing is accepted, rather than everything.
        if ($secret === '' || ! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $payload = Validator::make($request->json()->all(), [
            'id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'paid_at' => ['required', 'date'],
            'metadata.company' => ['required', 'string'],
            'metadata.invoice' => ['nullable', 'string'],
        ])->validate();

        $company = Company::where('slug', $payload['metadata']['company'])->first();

        if ($company === null) {
            return response()->json(['message' => 'Unknown company.'], 422);
        }

        // The gateway retries; a repeated event must not create a second payment.
        if (Payment::where('provider_reference', $payload['id'])->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        DB::transaction(function () use ($company, $payload) {
            $invoice = isset($payload['metadata']['invoice'])
                ? $company->invoices()->where('number', $payload['metadata']['invoice'])->first()
                : null;

            $payment = new Payment([
                'amount' => $payload['amount'],
                'currency' => strtoupper($payload['currency']),
                'provider_reference' => $payload['id'],
                'paid_at' => $payload['paid_at'],
            ]);
            $payment->company_id = $company->id;
            $payment->invoice_id = $invoice?->id;
            $payment->save();

            if ($invoice !== null && $invoice->status === InvoiceStatus::Sent && $invoice->payments()->sum('amount') >= $invoice->total) {
                $invoice->markAs(InvoiceStatus::Paid);
            }
        });

        return response()->json(['status' => 'recorded'], 201);
    }
}
