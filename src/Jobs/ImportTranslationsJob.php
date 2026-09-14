<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\ImportTranslationService;

class ImportTranslationsJob extends BaseJob
{
    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        resolve(ImportTranslationService::class)->importTranslations($this->batch(), $this->processLock());
    }
}
