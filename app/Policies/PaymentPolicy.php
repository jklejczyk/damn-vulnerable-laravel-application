<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\OperatorCanDoEverything;

class PaymentPolicy
{
    use OperatorCanDoEverything;

    public function view(User $user, Payment $payment): bool
    {
        return $user->belongsToCompany($payment->company_id) && $user->managesInvoices();
    }

    public function viewAny(User $user): bool
    {
        return $user->managesInvoices();
    }

    /**
     * Assign an unmatched payment to an invoice of the same company.
     */
    public function assign(User $user, Payment $payment): bool
    {
        return $user->belongsToCompany($payment->company_id) && $user->managesInvoices();
    }
}
