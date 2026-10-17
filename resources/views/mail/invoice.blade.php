<x-mail::message>
# {{ __('Invoice :number', ['number' => $invoice->number]) }}

{{ __(':company sent you an invoice for :total, due :due.', [
    'company' => $invoice->company->name,
    'total' => App\Support\Money::format($invoice->total, $invoice->currency),
    'due' => $invoice->due_date->toDateString(),
]) }}

{{ __('The invoice is attached as a PDF.') }}

{{ __('Thanks,') }}<br>
{{ $invoice->company->name }}
</x-mail::message>
