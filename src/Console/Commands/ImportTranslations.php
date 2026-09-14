<?php

namespace AnyMedia\Interpresso\Console\Commands;


use Illuminate\Console\Command;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ImportTranslationService;

class ImportTranslations extends Command
{
    use ChecksForRunningJobs;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'interpresso:import-translations';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports all the translations from in the filesystem.';

    /**
     * Execute the console command.
     */
    public function handle(ImportTranslationService $importTranslationService): void
    {
        if (($lock = $this->acquireProcessLock((string) $this->getName(), true)) === null) return;

        try {

            $totalTranslationsBefore = Translation::count();

            $this->info('Existing Translations: ' . $totalTranslationsBefore . '.');

            $this->info('Importing translations...');
            $importTranslationService->importTranslations(lock: $lock);
//            Translator::notifyAdminImportedTranslations($totalTranslationsBefore);
            $total = Translation::count() - $totalTranslationsBefore;
            $this->info('New translations imported: ' . $total . '.');

        } finally {
            $lock->release();
        }
    }
}
