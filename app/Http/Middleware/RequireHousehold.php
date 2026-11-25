<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireHousehold
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = $request->user()->activeMembership()->first();

        if (! $membership) {
            return redirect()->route('households.onboarding');
        }

        $householdId = $request->route('household');
        abort_if($householdId !== null && (string) $membership->household_id !== $householdId, 404);

        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
