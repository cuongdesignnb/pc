<?php

namespace App\Http\Middleware;

use App\Services\AdminApi\AdminApiTokens;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API access only with a token issued in Admin → API token. Customer
 * login tokens (ability "*") and browser sessions are refused even though
 * auth:sanctum accepts them.
 */
class EnsureAdminApiToken
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken
            || ! str_starts_with((string) $token->name, AdminApiTokens::NAME_PREFIX)
            || ! $user->isStaff()) {
            return response()->json(['message' => 'Cần API token quản trị.'], 401);
        }
        if (! in_array($ability, (array) $token->abilities, true)) {
            return response()->json(['message' => 'Token này không có quyền thực hiện thao tác.'], 403);
        }

        return $next($request);
    }
}
