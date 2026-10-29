<?php

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->user();

        if (! $agent instanceof Agent || ! $agent->status) {
            return response()->json([
                'success' => false,
                'message' => 'This agent account is inactive.',
            ], 403);
        }

        return $next($request);
    }
}
