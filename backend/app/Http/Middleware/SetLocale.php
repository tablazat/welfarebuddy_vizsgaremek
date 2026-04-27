<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    private const SUPPORTED = ['hu', 'en', 'de'];
    private const DEFAULT   = 'en';

    public function handle(Request $request, Closure $next)
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $userLocale = $request->user()?->locale;
        if (in_array($userLocale, self::SUPPORTED, true)) {
            return $userLocale;
        }

        $header = $request->header('Accept-Language', '');
        foreach (explode(',', $header) as $tag) {
            $code = strtolower(trim(explode(';', $tag)[0]));
            $primary = explode('-', $code)[0];
            if (in_array($primary, self::SUPPORTED, true)) {
                return $primary;
            }
        }

        return self::DEFAULT;
    }
}
