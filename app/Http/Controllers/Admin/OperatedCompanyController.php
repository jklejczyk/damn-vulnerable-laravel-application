<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * Lets the operator work inside a company with every page of the app, and leave it again.
 */
class OperatedCompanyController extends Controller
{
    public function store(Company $company): RedirectResponse
    {
        session(['operator.company_id' => $company->id]);

        return redirect()->route('dashboard');
    }

    public function destroy(): RedirectResponse
    {
        session()->forget('operator.company_id');

        return redirect()->route('admin.companies');
    }
}
