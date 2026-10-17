<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\OperatorCanDoEverything;

/**
 * Every member sees the clients of their company (to pick one on a draft); owners and
 * accountants manage them.
 */
class ClientPolicy
{
    use OperatorCanDoEverything;

    public function viewAny(User $user): bool
    {
        return $user->company_id !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->belongsToCompany($client->company_id);
    }

    public function create(User $user): bool
    {
        return $user->managesInvoices();
    }

    public function update(User $user, Client $client): bool
    {
        return $user->belongsToCompany($client->company_id) && $user->managesInvoices();
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->update($user, $client) && ! $client->invoices()->exists();
    }
}
