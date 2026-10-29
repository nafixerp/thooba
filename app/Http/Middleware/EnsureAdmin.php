<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next, ?string $role = null): Response
    {
        $user = $request->user();

        if (! $user || ! $user->status) {
            return redirect()->route('login')->withErrors(['email' => 'Please sign in to continue.']);
        }

        if ($role === 'accounts' && ! $user->canAccounts()) {
            abort(403, 'Accounts module access denied.');
        }

        if ($role === 'superadmin' && $user->role !== 'SUPERADMIN') {
            abort(403, 'Super admin access required.');
        }

        return $next($request);
    }
}
