<?php

namespace App\Http\Middleware;

use App\Models\Printer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a local print agent by its Bearer token. The resolved
 * printer is available to controllers as $request->attributes->get('printer').
 */
class AuthenticatePrinter
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $printer = $token ? Printer::findActiveByToken($token) : null;

        if ($printer === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $request->attributes->set('printer', $printer);

        return $next($request);
    }
}
