<?php

namespace AnyMedia\Interpresso\Console\Commands;

use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\MissingTranslationService;

class FindMissingTranslations extends Command
{
    use ChecksForRunningJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:find-missing-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates missing translations from other languages in the DB.';

    /**
     * Execute the console command.
     */
    public function handle(MissingTranslationService $missingTranslationService): void
    {
        if($this->anotherJobIsRunning(true)) return;
        try {
            Setting::setJobsRunning();

            /** @var list<int|numeric-string> $total SQL COUNT results, whose scalar type depends on the driver. */
            $total = Translation::selectRaw('count(*) as total')->groupBy('language_id')->orderBy('language_id')->pluck('total')->all();

            Language::query()->whereDoesntHave('translations')->each(function(Language $language) use (&$total) {
                $total[] = -1;
            });

            $total =  count(array_unique($total));

            if ($total > 1) {
                $totalTranslationsBefore = Translation::count();
                $this->info('Existing Translations: ' . $totalTranslationsBefore . '.');
                $this->info('Importing translations...');
                $missingTranslationService->findMissingTranslations();
                $rootLanguage = Language::query()->where('code', config('app.locale'))->first();
                if($rootLanguage) {
                    Translator::notifyAdminImportedMissingTranslations($totalTranslationsBefore, $rootLanguage);
                }
                $total = Translation::count() - $totalTranslationsBefore;
                $this->info('New missing translations created: ' . $total . '.');
            } else {
                $this->info('Everything up to date.');
            }
        } finally {
            Setting::setJobsRunning(false);
        }
    }
}
