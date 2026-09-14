<?php

namespace AnyMedia\Interpresso\Services;

use AnyMedia\Interpresso\Jobs\SendTranslatorPasswordReset;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\TranslatorPasswordLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class TranslatorPasswords
{
    public static function expiryMinutes(): int
    {
        $minutes = filter_var(config('interpresso.password_reset.expire', 60), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($minutes === false) {
            throw new \InvalidArgumentException('interpresso.password_reset.expire must be a positive number of minutes.');
        }
        return $minutes;
    }

    public function broker(): PasswordBroker
    {
        return Password::broker('interpresso_translators');
    }

    public function requestLink(string $email, string $locale): void
    {
        $connection = config('interpresso.password_reset.queue_connection');
        if (!is_string($connection) || !QueueConfiguration::connectionDefersWork($connection)) {
            throw new \LogicException('Translator password resets require a persistent asynchronous queue connection.');
        }
        /** @var string $queue */
        $queue = config('interpresso.queue_name');
        // Deliberately no account lookup here, including on repeated requests.
        Queue::connection($connection)->push(new SendTranslatorPasswordReset($email, $locale), '', $queue);
    }

    public function sendLink(string $email, string $locale, bool $invitation = false): void
    {
        $model = new Translator();
        $model->getConnection()->transaction(function () use ($email, $locale, $invitation): void {
            // Serialize issuance and redemption for a translator. This makes
            // concurrent resets single-use, beyond the broker's sequential check.
            $translator = Translator::query()->where('email', $email)->lockForUpdate()->first();
            if ($translator === null || ($invitation && $translator->password !== null)) {
                return;
            }

            $broker = $this->broker();
            $notify = function (Translator $translator, string $token) use ($locale, $invitation): void {
                $language = resolve(InterfaceLocales::class)->resolve($translator->locale, $locale, config('interpresso.locale'), config('app.locale'));
                $translator->notify((new TranslatorPasswordLink($token, $invitation))->locale($language));
            };

            if ($invitation) {
                // Resending replaces the previous link; it never changes a password.
                $notify($translator, $broker->createToken($translator));
            } else {
                $broker->sendResetLink(['email' => $translator->email], $notify);
            }
        });
    }

    /** @param array{email: string, token: string, password: string, password_confirmation: string} $credentials */
    public function reset(#[\SensitiveParameter] array $credentials): bool
    {
        return (new Translator())->getConnection()->transaction(function () use ($credentials): bool {
            Translator::query()->where('email', $credentials['email'])->lockForUpdate()->first();

            // Missing accounts must also pass through the broker's timebox.
            return $this->broker()->reset($credentials, function (Translator $translator, string $password): void {
                $translator->password = Hash::make($password);
                $translator->setRememberToken(Str::random(60));
                $translator->save();
                event(new PasswordReset($translator));
            }) === Password::PASSWORD_RESET;
        });
    }
}
