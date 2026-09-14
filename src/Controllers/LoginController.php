<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use AnyMedia\Interpresso\Requests\LoginRequest;

class LoginController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (Auth::guard($this->guard())->check()) {
            return redirect()->route('interpresso.languages');
        }

        return view('interpresso::login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        // An IP-scoped limit also prevents bypassing the limit by changing email.
        $key = 'interpresso:login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => __('interpresso::login.throttled', ['seconds' => $seconds]),
            ]);
        }
        RateLimiter::hit($key, 60);

        if (!Auth::guard($this->guard())->attempt($request->safe()->only(['email', 'password']), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('interpresso::login.invalid_credentials')]);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->route('interpresso.languages');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard($this->guard())->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('interpresso.login');
    }

    private function guard(): string
    {
        /** @var string $guard Configured session guard. */
        $guard = config('interpresso.translator_guard');
        return $guard;
    }
}
