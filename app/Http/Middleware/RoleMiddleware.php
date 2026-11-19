<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batasi rute ke role tertentu.
 * Pemakaian: ->middleware('role:kitchen') atau ->middleware('role:cashier,admin')
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = collect(explode(',', $roles))
            ->map(fn (string $role) => UserRole::tryFrom(trim($role)))
            ->filter()
            ->all();

        if (! in_array($user->role, $allowed, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
