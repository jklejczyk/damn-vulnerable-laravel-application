<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;
use App\Policies\Concerns\OperatorCanDoEverything;

/**
 * Company settings, team members and API tokens belong to the owners.
 */
class CompanyPolicy
{
    use OperatorCanDoEverything;

    public function view(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company->id);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company->id) && $user->isOwner();
    }

    public function manageMembers(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }

    public function manageTokens(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }
}
