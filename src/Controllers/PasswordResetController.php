<?php

namespace AnyMedia\Interpresso\Controllers;

use AnyMedia\Interpresso\Requests\RequestTranslatorPasswordReset;
use AnyMedia\Interpresso\Requests\ResetTranslatorPassword;
use AnyMedia\Interpresso\Services\TranslatorPasswords;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Throwable;

class PasswordResetController extends Controller
{
    public function requestForm(): View
    {
        return view('interpresso::forgot-password');
    }

    public function sendLink(RequestTranslatorPasswordReset $request, TranslatorPasswords $passwords): RedirectResponse
    {
        try {
            $passwords->requestLink($request->string('email')->toString(), app('translator')->getLocale());
        } catch (Throwable $exception) {
            // Infrastructure failures must not change the public response either.
            report($exception);
        }

        return redirect()->route('interpresso.password.request')
            ->with('password_status', __('interpresso::passwords.request_sent'));
    }

    public function resetForm(Request $request, string $token): View
    {
        return view('interpresso::reset-password', [
            'token' => $token,
            'email' => is_string($request->query('email')) ? $request->query('email') : '',
            'invitation' => $request->boolean('invitation'),
        ]);
    }

    public function reset(ResetTranslatorPassword $request, TranslatorPasswords $passwords): RedirectResponse
    {
        /** @var array{email: string, token: string, password: string, password_confirmation: string} $credentials */
        $credentials = $request->safe()->only(['email', 'token', 'password', 'password_confirmation']);
        if (!$passwords->reset($credentials)) {
            throw ValidationException::withMessages(['email' => __('interpresso::passwords.invalid_token')]);
        }

        return redirect()->route('interpresso.login')->with('password_status', __('interpresso::passwords.reset_done'));
    }
}
