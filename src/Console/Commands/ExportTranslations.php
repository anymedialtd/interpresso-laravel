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
    protected $signature = 'interpresso:export-translations {--force=} {--language=} {--only-models}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Exports all approved translations.';

    /**
     * Execute the console command.
     */
    public function handle(ExportTranslationService $exportTranslationService): int
    {
        $forceExport = (bool) $this->option('force');
        $code = $this->option('language');
        if ($code !== null && !Language::query()->where('code', $code)->exists()) {
            $this->error(__('interpresso::commands.language_not_found'));
            return self::FAILURE;
        }
        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) return self::SUCCESS;
        try {
            $onlyModels = (bool) $this->option('only-models') || Setting::getCached()->db_loader;
            $query = Translation::query()
                ->isUpdated(false)
                ->when($code !== null, fn ($query) => $query->where('language_code', $code))
                ->when($onlyModels, fn ($query) => $query->type('model'))
                ->when(!$forceExport, function($query) {
                    $query->exported(false);
                })
                ->approved();
            $languages = Language::query()->whereIn('id', (clone $query)->distinct()->pluck('language_id'))->get();

            if (count($languages)) {
                $total = (clone $query)->count();
                $this->info('Exporting translations...');
                foreach ($languages as $language) {
                    $lock->refresh();
                    if($forceExport) {
                        $exportTranslationService->forceExportTranslationForLanguage($language, null, $onlyModels);
                    } else {
                        $exportTranslationService->exportTranslationForLanguage($language, null, $onlyModels);
                    }
                }
                Translator::notifyAdminExportedTranslationsAllLanguages($total, $languages);
                $total -= (clone $query)->exported(false)->count();
                $this->info('Total translations exported: ' . $total . '.');

            } else {
                $this->info('Nothing to export.');
            }
            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }

}
