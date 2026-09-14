<?php

namespace AnyMedia\Interpresso\Middleware;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\InterfaceLocales;
use Closure;
use Illuminate\Contracts\Translation\Translator as Translation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetInterfaceLocale
{
    public function __construct(private InterfaceLocales $locales, private Translation $translation) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var string $guard Configured translator session guard. */
        $guard = config('interpresso.translator_guard');
        $translator = $request->hasSession() ? auth($guard)->user() : null;
        $locale = $this->locales->resolve(
            $translator instanceof Translator ? $translator->locale : null,
            $request->cookie('interpresso-locale'),
            config('interpresso.locale'),
            config('app.locale'),
        );

        $previousLocale = $this->translation->getLocale();
        // App::setLocale also changes app.locale, the source language used by
        // imports and bulk actions. Only the interface translation service changes.
        $this->translation->setLocale($locale);
        try {
            return $next($request);
        } finally {
            $this->translation->setLocale($previousLocale);
        }
    }
}
