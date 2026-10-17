<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVinEntered
{
    /**
     * Holds only the VIN's hash and masked form; sessions are stored server-side, so the raw VIN must never be stored here.
     */
    public const string SESSION_KEY = 'report_vin';

    /**
     * The ID of the report this session filed, or came back to add a result sheet to.
     */
    public const string REPORT_KEY = self::SESSION_KEY.'.report_id';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            if ($request->expectsJson()) {
                abort(401, 'Enter your VIN first.');
            }

            return to_route('vin.create');
        }

        return $next($request);
    }
}
