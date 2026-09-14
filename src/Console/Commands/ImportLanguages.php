<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ImportLanguageService;

class ImportLanguages extends Command
{
    use ChecksForRunningJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:import-languages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports all the languages which are in the filesystem.';

    /**
     * Execute the console command.
     */
    public function handle(ImportLanguageService $importLanguageService): void
    {
        if($this->anotherJobIsRunning(true)) return;
        try {
            Setting::setJobsRunning();

            $languages = Language::all();
            if($languages->count()) {
                /** @var list<string> $names Language model name attributes. */
                $names = $languages->pluck('name')->all();
                $this->info('Existing languages: ' . implode(', ', $names) . '.');
            }

            /** @var list<int> $languages Language model primary keys. */
            $languages = $languages->pluck('id')->all();
            $importLanguageService->importLanguages();
            $newLanguages = Translator::notifyAdminImportedLanguages($languages);

            if($newLanguages) {
                $this->info('New languages imported: ' . implode(', ', $newLanguages) . '.');
            } else {
                $this->info('Nothing imported.');
            }

        } finally {
            Setting::setJobsRunning(false);
        }
    }
}
