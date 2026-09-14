<?php

namespace AnyMedia\Interpresso\Middleware;

use Closure;
use Illuminate\Http\Request;
use AnyMedia\Interpresso\Models\Translator;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Require an administrator resolved by EnsureTranslator.
     *
     * @param Request $request
     * @param Closure(Request): Response $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('authUser');

        if (!$authUser instanceof Translator || !$authUser->admin) {
            abort(403);
        }

        return $next($request);
    }
}
