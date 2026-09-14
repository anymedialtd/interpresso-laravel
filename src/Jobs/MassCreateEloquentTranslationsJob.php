<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\BaseJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\Traits\CanCreateTranslation;

class MassCreateEloquentTranslationsJob extends BaseJob
{
    use CanCreateTranslation;

    public int $timeout = 300;

    /**
     * @param list<int> $translationIds
     */
    public function __construct(
        protected array  $translationIds,
        protected int $languageId,
        protected int $fromLanguageId
    )
    {
        /** @var string|null $queue Configured queue name stored by Queueable. */
        $queue = config('interpresso.queue_name');
        $this->queue = $queue;
        parent::__construct();
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        $this->massCreateEloquentTranslations(
            $this->translationIds,
            Language::query()->findOrFail($this->languageId),
            Language::query()->findOrFail($this->fromLanguageId)
        );
    }
}
