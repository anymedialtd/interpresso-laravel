<?php

namespace AnyMedia\Interpresso\Console\Commands;

use Illuminate\Console\Command;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ApproveLanguagesService;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;

class ApproveTranslations extends Command
{
    use ChecksForRunningJobs;

    protected $signature = 'interpresso:approve-translations {--translator=} {--language=}';

    protected $description = 'Approves translations for all languages or one language, attributed to an administrator.';

    public function handle(ApproveLanguagesService $service): int
    {
        $translatorId = $this->option('translator');
        $translator = is_string($translatorId) && ctype_digit($translatorId)
            ? Translator::query()->admin()->find($translatorId) : null;
        if ($translator === null) {
            $this->error(__('interpresso::commands.approval_translator_required'));
            return self::FAILURE;
        }

        $code = $this->option('language');
        $languages = Language::query()->when($code !== null, fn ($query) => $query->where('code', $code))->get();
        if ($code !== null && $languages->isEmpty()) {
            $this->error(__('interpresso::commands.language_not_found'));
            return self::FAILURE;
        }

        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) {
            return self::SUCCESS;
        }
        try {
            $total = 0;
            foreach ($languages as $language) {
                $lock->refresh();
                $count = $language->translations()->where('approved', false)->count();
                if ($count === 0) continue;
                // CLI work stays in this process, including when the queue is sync.
                $service->approveLanguages($language, $translator->id);
                Translator::notifyAdminApprovedTranslationsPerLanguage($count, $language);
                $total += $count;
            }
            $this->info(__('interpresso::commands.translations_approved', ['total' => $total]));
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
