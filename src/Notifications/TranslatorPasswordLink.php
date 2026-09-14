<?php

namespace AnyMedia\Interpresso\Notifications;

use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\TranslatorPasswords;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TranslatorPasswordLink extends Notification
{
    public function __construct(public string $token, public bool $invitation = false) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function url(Translator $translator): string
    {
        // Build from the configured origin, never an untrusted request Host header.
        /** @var string $origin */
        $origin = config('interpresso.main_server_domain');
        return rtrim($origin, '/') . route('interpresso.password.reset', [
            'token' => $this->token,
            'email' => $translator->email,
            'invitation' => $this->invitation ? 1 : 0,
        ], false);
    }

    public function toMail(Translator $notifiable): MailMessage
    {
        $kind = $this->invitation ? 'invite' : 'reset';
        return (new MailMessage())
            ->subject(__('interpresso::passwords.' . $kind . '_subject'))
            ->greeting(__('interpresso::passwords.greeting', ['name' => $notifiable->first_name]))
            ->line(__('interpresso::passwords.' . $kind . '_intro'))
            ->action(__('interpresso::passwords.' . $kind . '_action'), $this->url($notifiable))
            ->line(__('interpresso::passwords.expires', ['minutes' => TranslatorPasswords::expiryMinutes()]))
            ->line(__('interpresso::passwords.' . $kind . '_ignore'))
            ->salutation(__('interpresso::passwords.salutation'))
            ->view('interpresso::emails.password-link');
    }
}
