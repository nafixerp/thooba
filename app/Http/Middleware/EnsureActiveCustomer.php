<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A customer blocked in the admin panel must not keep using an old token.
 *
 * Login already refuses them, but a token issued before they were blocked
 * stayed valid because auth:sanctum only checks the token, never the account.
 * This mirrors EnsureActiveAgent on the agent side.
 */
class EnsureActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $customer = $request->user();

        if (! $customer instanceof Customer || ! $customer->status) {
            return response()->json([
                'success' => false,
                'message' => 'This account is inactive. Please contact support.',
            ], 403);
        }

        return $next($request);
    }
}
