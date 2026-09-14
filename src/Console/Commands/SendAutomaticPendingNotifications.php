<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use Illuminate\Support\Facades\DB;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\PendingTranslationsNotification;

class SendAutomaticPendingNotifications extends Command
{
    use ChecksForRunningJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:send-automatic-pending-translations-notification';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends pending translations notifications to the translators..';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) return;
        try {
            if(Setting::getCached()->enable_automatic_pending_notifications) {
                Translator::query()->each(function (Translator $translator) {
                    $translator->languages()->each(function (Language $language) use ($translator) {
                        $translator->notify(new PendingTranslationsNotification($language));
                    });
                });
            }
        } finally {
            $lock->release();
        }
    }
}
