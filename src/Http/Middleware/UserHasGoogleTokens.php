<?php

namespace Condoedge\Messaging\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Redirect;

class UserHasGoogleTokens
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
        $accessToken = getCurrentGoogleToken();

        if (!$accessToken) {
            return Redirect::route('google-sso');
        }

        return $next($request);
    }
}
