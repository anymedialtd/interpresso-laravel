<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\ExportTranslationService;

class ForceExportTranslationJob extends BaseJob
{
    public function __construct(
        protected Language                 $language,
        protected bool $exportOnlyModels = false
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        resolve(ExportTranslationService::class)->forceExportTranslationForLanguage($this->language, $this->batch(), $this->exportOnlyModels);
    }

}
