<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ExportTranslationService;

class ExportTranslations extends Command
{
    use ChecksForRunningJobs;

    /**
     * The name and signature of the console command.
     * --force: it will export all files even if exported is true in the translations record
     *
     * @var string
     */
    protected $signature = 'interpresso:export-translations {--force=}';

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
        $forceExport = (bool) $this->option('force');
        if($this->anotherJobIsRunning(true)) return;
        try {
            Setting::setJobsRunning();
            $languages = Language::find(Translation::query()
                ->isUpdated(false)
                ->when(!$forceExport, function($query) {
                    $query->exported(false);
                })
                ->approved()->distinct()->pluck('language_id')->toArray());

            if (count($languages)) {
                $total = Translation::query()
                    ->isUpdated(false)
                    ->when(!$forceExport, function($query) {
                        $query->exported(false);
                    })
                    ->approved()
                    ->count();
                $this->info('Exporting translations...');
                Language::query()->each(function (Language $language) use ($exportTranslationService, $forceExport) {
                    if($forceExport) {
                        $setting = Setting::getCached();
                        $exportTranslationService->forceExportTranslationForLanguage($language, null, $setting->db_loader);
                    } else {
                        $setting = Setting::getCached();
                        $exportTranslationService->exportTranslationForLanguage($language, null, $setting->db_loader);
                    }
                });
                Translator::notifyAdminExportedTranslationsAllLanguages($total, $languages);
                $total -= Translation::query()
                    ->isUpdated(false)->exported(false)
                    ->approved()
                    ->count();
                $this->info('Total translations exported: ' . $total . '.');

            } else {
                $this->info('Nothing to export.');
            }
        } finally {
            Setting::setJobsRunning(false);
        }
    }

}
