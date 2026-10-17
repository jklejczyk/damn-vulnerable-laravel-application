<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    /**
     * DVLA-014: tokens are created without an expiry date and sanctum.expiration is null.
     */
    public function store(Request $request): JsonResponse
    {
        // Tokens of the accounting integration belong to company owners.
        abort_if($request->user()->company === null, 403);
        $this->authorize('manageTokens', $request->user()->company);

        $validated = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $token = $request->user()->createToken($validated['name']);

        return response()->json(['token' => $token->plainTextToken], 201);
    }
}
