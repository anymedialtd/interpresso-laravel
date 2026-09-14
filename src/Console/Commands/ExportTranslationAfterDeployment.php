<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\ExportTranslationService;

class ExportTranslationAfterDeployment extends Command
{
    use ChecksForRunningJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:export-translations-deployment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports all approved translations.';

    /**
     * Execute the console command.
     */
    public function handle(ExportTranslationService $exportTranslationService): void
    {
        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) return;
        try {
            Language::query()->each(function(Language $language) use ($exportTranslationService) {
                $exportTranslationService->forceExportTranslationForLanguage($language);
                $this->info('Language: ' . $language->code . ' exported.');
            });
        } finally {
            $lock->release();
        }
    }
}
