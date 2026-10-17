<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\OperatorCanDoEverything;

/**
 * Members read invoices of their company and work on their own drafts; owners and accountants
 * manage every invoice of the company. Nobody outside the company gets in, except the operator (Concerns\OperatorCanDoEverything).
 */
class InvoicePolicy
{
    use OperatorCanDoEverything;

    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id);
    }

    public function create(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $this->worksOn($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->isDraft() && $this->worksOn($user, $invoice);
    }

    /**
     * Send, mark as paid or void.
     */
    public function changeStatus(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id) && $user->managesInvoices();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->changeStatus($user, $invoice);
    }

    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $this->create($user);
    }

    private function worksOn(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id)
            && ($user->managesInvoices() || $invoice->user_id === $user->id);
    }
}
