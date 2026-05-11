<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     * Check if user has the required permission(s).
     * Super-admin role bypasses all permission checks.
     *
     * @param string $permissions Pipe-separated permissions (e.g. "products.view|products.create")
     */
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('admin.login');
        }

        // Super-admin bypasses all permission checks
        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $permissionArray = explode('|', $permissions);

        if (!$user->hasAnyPermission($permissionArray)) {
            if ($request->header('X-Inertia')) {
                return inertia('Admin/Errors/Forbidden')->toResponse($request)->setStatusCode(403);
            }

            abort(403, 'Bạn không có quyền thực hiện thao tác này.');
        }

        return $next($request);
    }
}
