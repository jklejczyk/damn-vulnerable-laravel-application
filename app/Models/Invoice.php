<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property InvoiceStatus $status
 * @property Carbon $issue_date
 * @property Carbon $due_date
 * @property int $total in the smallest currency unit (grosze)
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * DVLA-019: every column is mass assignable, including company_id and status
     * (see Api\InvoiceController@store).
     */
    protected $guarded = [];

    /**
     * Same defaults as the columns, so a new model has them before it is reloaded.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'currency' => 'PLN',
        'total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'total' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * The team member who issued the invoice.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function isOverdue(): bool
    {
        return $this->status === InvoiceStatus::Sent && $this->due_date->isPast();
    }

    /**
     * Store the sum of the items as the total; amounts are integers, so there is no rounding.
     */
    public function recalculate(): void
    {
        $this->total = (int) $this->items()->get()->sum(fn (InvoiceItem $item): int => $item->lineTotal());
        $this->save();
    }

    /**
     * @param  'draft'|'sent'|'paid'|'void'|InvoiceStatus  $status
     */
    public function markAs(InvoiceStatus|string $status): void
    {
        $this->status = $status instanceof InvoiceStatus ? $status : InvoiceStatus::from($status);

        if ($this->status === InvoiceStatus::Sent && $this->sent_at === null) {
            $this->sent_at = now();
        }

        $this->save();
    }
}
