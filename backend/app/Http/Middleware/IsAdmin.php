<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    /**
     * Admin middleware – csak admin (level_of_access = 'admin') felhasználók férhetnek hozzá.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->level_of_access !== 'admin') {
            return response()->json(['message' => 'Hozzáférés megtagadva.'], 403);
        }

        return $next($request);
    }
}
