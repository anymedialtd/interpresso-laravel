<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\ImportLanguageService;

class ImportLanguagesJob extends BaseJob
{
    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        resolve(ImportLanguageService::class)->importLanguages();
    }
}
