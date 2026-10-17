<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * DVLA-SAFE-003: only validated fields are saved and company_id is not fillable, so a
     * company_id in the request is ignored.
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Client::class);
        abort_if($request->user()->company === null, 403, 'The token does not belong to a company.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:1000'],
        ]);

        $client = $request->user()->company->clients()->create($validated);

        return response()->json($client->only('id', 'name', 'email', 'tax_id', 'address'), 201);
    }
}
