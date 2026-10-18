<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;

class ClientInvoiceController extends Controller
{
    /**
     * DVLA-022: only the client is authorized and the binding is not scoped, so any invoice id
     * works under any client the token may see, including invoices of other companies.
     */
    public function show(Client $client, Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $client);

        return new InvoiceResource($invoice->load('client', 'items'));
    }

    /**
     * DVLA-SAFE-005: {invoice:number} is a custom key, so Laravel loads the invoice through
     * $client->invoices() and an invoice of another client is a 404.
     */
    public function showByNumber(Client $client, Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $client);

        return new InvoiceResource($invoice->load('client', 'items'));
    }

    /**
     * DVLA-SAFE-005: the route group uses scopeBindings().
     */
    public function items(Client $client, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $client);

        return response()->json($invoice->items->map->only('description', 'quantity', 'unit_price'));
    }
}
