<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\MissingTranslationService;

class FindMissingTranslationsJob extends BaseJob
{
    /**
     * @return void
     * @throws \AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException
     */
    public function handle(): void
    {
        resolve(MissingTranslationService::class)->findMissingTranslations($this->batch());
    }
}
