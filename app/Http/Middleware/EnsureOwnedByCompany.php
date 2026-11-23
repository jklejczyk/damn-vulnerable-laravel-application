<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DVLA-SAFE-006: "owned:client" lets the request through only when the bound model belongs to the
 * user's company, so the action itself does not authorize.
 */
class EnsureOwnedByCompany
{
    public function handle(Request $request, Closure $next, string $parameter): Response
    {
        $model = $request->route($parameter);

        abort_unless($model instanceof Model && $request->user()?->belongsToCompany($model->getAttribute('company_id')), 404);

        return $next($request);
    }
}
