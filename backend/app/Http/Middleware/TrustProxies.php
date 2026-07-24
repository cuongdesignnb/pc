<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Apply trusted proxy headers and synchronize the URL generator's request cache.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return parent::handle($request, function (Request $request) use ($next) {
            app('url')->setRequest($request);

            return $next($request);
        });
    }
}
