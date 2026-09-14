<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\ChunkedJob;
use AnyMedia\Interpresso\Services\ImportSource;
use AnyMedia\Interpresso\Services\ImportTranslationService;

class ImportTranslationsJob extends ChunkedJob
{
    /** @var list<ImportSource>|null */
    protected ?array $sources = null;
    protected int $sourceIndex = 0;
    public int|string|null $afterId = null;

    /**
     * @return void
     * @throws \Exception
     */
    public function handle(): void
    {
        if (!$this->startChunk()) return;
        $service = resolve(ImportTranslationService::class);
        if ($this->batch() === null) {
            $service->importTranslations();
            return;
        }
        $this->sources ??= $service->sources();
        $source = $this->sources[$this->sourceIndex] ?? null;
        if ($source === null) return;
        $lastId = $service->importChunk($source, $this->afterId, $this->chunkSize);
        $next = clone $this;
        $next->afterId = $lastId;
        if ($lastId === null) $next->sourceIndex++;
        $this->finishChunk(isset($this->sources[$next->sourceIndex]) ? $next : null);
    }

    public function estimatedJobs(): int
    {
        $this->sources ??= resolve(ImportTranslationService::class)->sources();
        return max(1, array_sum(array_map(fn (ImportSource $source): int => $source->estimatedJobs($this->chunkSize), $this->sources)));
    }
}
