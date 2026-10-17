<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'client' => ['id' => $this->client_id, 'name' => $this->whenLoaded('client', fn () => $this->client->name)],
            'issue_date' => $this->issue_date->toDateString(),
            'due_date' => $this->due_date->toDateString(),
            'currency' => $this->currency,
            'total' => $this->total,
            'items' => $this->whenLoaded('items', fn () => $this->items->map->only('description', 'quantity', 'unit_price')),
        ];
    }
}
