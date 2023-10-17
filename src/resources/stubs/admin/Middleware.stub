<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::user()->is_admin == '1') {
            return redirect('/')->with('status', 'Access Denied. You are not an admin!');
        } else {
            return $next($request);
        }
    }
}
