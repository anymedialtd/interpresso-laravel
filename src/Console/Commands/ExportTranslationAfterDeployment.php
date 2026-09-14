<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ForceExportTranslationJob;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;
use AnyMedia\Interpresso\Services\ExportTranslationService;

class ExportTranslationAfterDeployment extends Command
{

    /**
     * The name and signature of the console command.
     * --force: it will export all files even if exported is true in the translations record
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
        Language::query()->each(function(Language $language) use ($exportTranslationService) {
            $exportTranslationService->forceExportTranslationForLanguage($language);
            $this->info('Language: ' . $language->code . ' exported.');
        });
    }
}
