<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Services\Traits\CanCreateTranslation;

class MassCreateTranslationsJob extends BaseJob
{
    use CanCreateTranslation;

    /**
     * @param array<int|string, string> $content
     */
    public function __construct(
        protected array  $content,
        protected string $type,
        protected int    $languageId,
        protected string $languageCode,
        protected string $namespace,
        protected string $group,
        protected bool $isVendor
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
        $this->massCreateTranslations($this->content, $this->type, $this->languageId, $this->languageCode, $this->namespace, $this->group, $this->isVendor);
    }
}
