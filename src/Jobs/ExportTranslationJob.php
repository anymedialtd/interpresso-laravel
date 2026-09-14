<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\ChunkedJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Services\ExportTranslationService;

class ExportTranslationJob extends ChunkedJob
{
    protected bool $forceExportAll = false;
    public int $afterId = 0;

    public function __construct(
        protected Language                 $language,
        protected bool $exportOnlyModels = false,
        int $afterId = 0,
    )
    {
        parent::__construct();
        $this->afterId = $afterId;
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        if (!$this->startChunk()) return;
        $service = resolve(ExportTranslationService::class);
        if ($this->batch() === null) {
            if ($this->forceExportAll) {
                $service->forceExportTranslationForLanguage($this->language, null, $this->exportOnlyModels);
            } else {
                $service->exportTranslationForLanguage($this->language, null, $this->exportOnlyModels);
            }
            return;
        }
        $lastId = $service->exportChunk($this->language, $this->afterId, $this->chunkSize, $this->exportOnlyModels, $this->forceExportAll);
        $next = null;
        if ($lastId !== null) {
            $next = clone $this;
            $next->afterId = $lastId;
        }
        $this->finishChunk($next);
    }

    public function estimatedJobs(): int
    {
        return $this->estimateChunks(Translation::query()->where('language_id', $this->language->id)
            ->where('id', '>', $this->afterId)
            ->when($this->exportOnlyModels, fn ($query) => $query->where('type', 'model'))->count());
    }
}
