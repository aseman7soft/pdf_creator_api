<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks deactivated accounts even if a session is still valid.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->is_active !== true) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => ['code' => 'ACCOUNT_DISABLED', 'message' => 'حساب کاربری غیرفعال است.'],
                ], 403);
            }

            return redirect()->route('admin.login')
                ->withErrors(['email' => 'حساب کاربری شما غیرفعال شده است.']);
        }

        return $next($request);
    }
}
