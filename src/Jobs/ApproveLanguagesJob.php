<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\ChunkedJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\ApproveLanguagesService;

class ApproveLanguagesJob extends ChunkedJob
{
    public int $afterId = 0;

    public function __construct(
        protected Language $language,
        protected int $authUserId,
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
        $service = resolve(ApproveLanguagesService::class);
        if ($this->batch() === null) {
            $service->approveLanguages($this->language, $this->authUserId);
            return;
        }
        $lastId = $service->approveChunk($this->language, $this->authUserId, $this->afterId, $this->chunkSize);
        $next = null;
        if ($lastId !== null) {
            $next = clone $this;
            $next->afterId = $lastId;
        }
        $this->finishChunk($next);
    }

    public function estimatedJobs(): int
    {
        return $this->estimateChunks($this->language->translations()->where('id', '>', $this->afterId)->count());
    }
}
