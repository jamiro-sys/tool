<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NurAktive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user && ! $user->aktiv) {
            Auth::logout();

            return redirect()->route('anmelden')->withErrors(['email' => 'Dein Zugang ist gesperrt.']);
        }

        return $next($request);
    }
}
