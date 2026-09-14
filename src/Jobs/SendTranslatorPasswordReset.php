<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Services\TranslatorPasswords;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendTranslatorPasswordReset implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public string $email, public string $locale) {}

    public function handle(TranslatorPasswords $passwords): void
    {
        $passwords->sendLink($this->email, $this->locale);
    }
}
