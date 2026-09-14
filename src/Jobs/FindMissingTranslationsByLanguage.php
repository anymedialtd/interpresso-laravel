<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\Traits\CanCreateTranslation;

class FindMissingTranslationsByLanguage extends BaseJob
{
    use CanCreateTranslation;

    /**
     * @param list<int> $languageIds
     */
    public function __construct(
        protected array $languageIds,
        protected int $languageId,
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     * @throws \AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException
     */
    public function handle(): void
    {
        $this->findMissingTranslationsByLanguage(
            Language::query()->whereIn('id', $this->languageIds)->get(),
            Language::query()->findOrFail($this->languageId),
            $this->batch()
        );
    }
}
