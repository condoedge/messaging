<?php

namespace Condoedge\Messaging\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redirect;

class UserHasMsGraphTokens
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $redirectToRoute
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next, $redirectToRoute = null)
    {
        $accessToken = getCurrentUserAccessToken();

        if (!$accessToken) {
            return Redirect::route('microsoft-sso');
        }

        return $next($request);
    }
}
