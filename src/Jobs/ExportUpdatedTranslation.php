<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\Traits\CanExportTranslation;

class ExportUpdatedTranslation extends BaseJob
{
    use CanExportTranslation;

    public function __construct(
        protected string $type,
        protected string $languageCode,
        protected bool $isVendor,
        protected string $namespace = '',
        protected string $group = '',
        protected bool $forceExportAll = false
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
        $this->updateTranslation($this->type, $this->languageCode, $this->isVendor, $this->namespace, $this->group, $this->forceExportAll);
    }

}
