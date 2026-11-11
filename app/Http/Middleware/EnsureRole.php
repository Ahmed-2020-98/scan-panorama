<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Usage: ->middleware('role:manager,reception'). 'staff' also lets in a technician the admin promoted.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && (in_array($user->role->value, $roles, true) || (in_array('staff', $roles, true) && $user->isStaff())), 403);

        return $next($request);
    }
}
