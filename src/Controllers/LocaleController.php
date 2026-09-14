<?php

namespace AnyMedia\Interpresso\Controllers;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Requests\UpdateLocaleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class LocaleController extends Controller
{
    public function update(UpdateLocaleRequest $request): RedirectResponse
    {
        /** @var string $locale Validated against the package's available locales. */
        $locale = $request->validated('locale');
        /** @var string $guard Configured translator session guard. */
        $guard = config('interpresso.translator_guard');
        $translator = auth($guard)->user();
        if ($translator instanceof Translator) {
            $translator->update(['locale' => $locale]);
        }

        /** @var string $prefix Configured package URL prefix. */
        $prefix = config('interpresso.prefix');
        $cookie = cookie('interpresso-locale', $locale, 60 * 24 * 365,
            parse_url(url($prefix), PHP_URL_PATH) ?: '/', null, $request->isSecure(), true, false, 'lax');

        return redirect()->back(fallback: route('interpresso.login'))->withCookie($cookie);
    }
}
