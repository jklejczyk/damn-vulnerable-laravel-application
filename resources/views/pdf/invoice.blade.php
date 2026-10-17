<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #27272a; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        .parties td { vertical-align: top; width: 50%; padding: 12px 0; }
        .items th { text-align: left; border-bottom: 1px solid #a1a1aa; padding: 6px 4px; }
        .items td { border-bottom: 1px solid #e4e4e7; padding: 6px 4px; }
        .num { text-align: right; }
        .total td { font-weight: bold; border-bottom: none; }
        .muted { color: #71717a; }
        .logo { max-height: 48px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @endif
                <h1>{{ __('Invoice') }} {{ $invoice->number }}</h1>
                <div class="muted">{{ __('Issued') }} {{ $invoice->issue_date->toDateString() }} · {{ __('Due') }} {{ $invoice->due_date->toDateString() }}</div>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="muted">{{ __('Seller') }}</div>
                <strong>{{ $invoice->company->name }}</strong><br>
                {!! nl2br(e($invoice->company->address)) !!}<br>
                {{ __('Tax ID') }}: {{ $invoice->company->tax_id }}
            </td>
            <td>
                <div class="muted">{{ __('Buyer') }}</div>
                <strong>{{ $invoice->client->name }}</strong><br>
                {!! nl2br(e($invoice->client->address)) !!}<br>
                {{ __('Tax ID') }}: {{ $invoice->client->tax_id }}
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Qty') }}</th>
                <th class="num">{{ __('Unit price') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ App\Support\Money::format($item->unit_price, $invoice->currency) }}</td>
                    <td class="num">{{ App\Support\Money::format($item->lineTotal(), $invoice->currency) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3" class="num">{{ __('Total') }}</td>
                <td class="num">{{ App\Support\Money::format($invoice->total, $invoice->currency) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($invoice->notes)
        <p>{!! nl2br(e($invoice->notes)) !!}</p>
    @endif
</body>
</html>
