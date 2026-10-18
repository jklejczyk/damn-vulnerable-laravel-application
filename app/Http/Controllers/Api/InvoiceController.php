<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Invoice::class);
        abort_if($request->user()->company === null, 403, 'The token does not belong to a company.');

        return InvoiceResource::collection(
            $request->user()->company->invoices()->with('client')->latest('issue_date')->paginate(50),
        );
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        $this->authorize('view', $invoice);

        return new InvoiceResource($invoice->load('client', 'items'));
    }

    /**
     * DVLA-019: the request is validated, but the whole request is saved. Invoice has an empty
     * $guarded and "+" keeps the request's keys, so a token can set status=paid or the
     * company_id of another company.
     */
    public function store(Request $request): Response
    {
        $this->authorize('create', Invoice::class);

        $company = $request->user()->company ?? abort(403, 'The token does not belong to a company.');

        $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $company->id)],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'currency' => ['sometimes', Rule::in(['PLN', 'EUR', 'USD'])],
            'items' => ['sometimes', 'array', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'integer', 'min:0'],
        ]);

        // Items go to their own table, so they leave the request before the invoice is saved.
        $items = $request->input('items', []);
        unset($request['items']);

        $invoice = DB::transaction(function () use ($request, $company, $items): Invoice {
            $invoice = Invoice::create($request->all() + [
                'company_id' => $company->id,
                'user_id' => $request->user()->id,
                'number' => $company->nextInvoiceNumber(),
            ]);

            foreach ($items as $item) {
                $invoice->items()->create($item);
            }

            $invoice->recalculate();

            return $invoice;
        });

        return response((new InvoiceResource($invoice->load('client', 'items')))->toJson(), 201, ['Content-Type' => 'application/json']);
    }

    /**
     * DVLA-021: any token may change any invoice; the action never asks InvoicePolicy.
     */
    public function update(Request $request, Invoice $invoice): InvoiceResource
    {
        $invoice->update($request->validate([
            'due_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]));

        return new InvoiceResource($invoice->load('client', 'items'));
    }

    /**
     * DVLA-020: added for a script that cleans up old drafts and routed outside auth:sanctum,
     * so anyone can delete any draft by its id.
     */
    public function destroy(Invoice $invoice): Response
    {
        abort_unless($invoice->isDraft(), 422, 'Only drafts can be deleted.');

        $invoice->delete();

        return response()->noContent();
    }
}
