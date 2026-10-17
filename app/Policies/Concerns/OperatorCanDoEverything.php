<?php

namespace App\Policies\Concerns;

use App\Models\User;

/**
 * The SaaS operator may do anything in any company: view, edit, change statuses, manage teams.
 * API tokens are the exception: a token acts for its owner's company, and the operator has none.
 */
trait OperatorCanDoEverything
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->is_admin && $ability !== 'manageTokens' ? true : null;
    }
}
