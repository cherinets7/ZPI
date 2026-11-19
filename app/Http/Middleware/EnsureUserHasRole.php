<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->status !== 'active') {
            abort(403);
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            abort(403);
        }

        $departmentRoles = config('workflow.department_roles', []);
        if (in_array($user->role, $departmentRoles, true)) {
            $department = $user->department;
            if (! $user->department_id || ! $department || ! $department->is_active) {
                abort(403);
            }
        }

        return $next($request);
    }
}
