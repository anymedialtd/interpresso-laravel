<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\ChunkedJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Services\Traits\CanCreateTranslation;

class FindMissingTranslationsByLanguage extends ChunkedJob
{
    use CanCreateTranslation;

    public int $afterId = 0;
    protected int $targetIndex = 0;

    /**
     * @param list<int> $languageIds
     */
    public function __construct(
        protected array $languageIds,
        protected int $languageId,
        int $afterId = 0,
        int $targetIndex = 0,
    )
    {
        parent::__construct();
        $this->afterId = $afterId;
        $this->targetIndex = $targetIndex;
        if (Setting::getCached()->enable_open_ai_translations) {
            $aiLimit = config('interpresso.max_open_ai_missing_trans', 50);
            if (!is_int($aiLimit) || $aiLimit < 1) {
                throw new \InvalidArgumentException('interpresso.max_open_ai_missing_trans must be a positive integer.');
            }
            $this->chunkSize = min($this->chunkSize, $aiLimit);
        }
    }

    /**
     * @return void
     * @throws \AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException
     */
    public function handle(): void
    {
        if (!$this->startChunk()) return;
        $root = Language::query()->findOrFail($this->languageId);
        if ($this->batch() === null) {
            $this->findMissingTranslationsByLanguage(Language::query()->whereIn('id', $this->languageIds)->get(), $root);
            return;
        }
        $targetId = $this->languageIds[$this->targetIndex] ?? null;
        if ($targetId === null) return;
        $target = Language::query()->findOrFail($targetId);
        // Source IDs never change when target rows are inserted. Existing shared
        // identifiers are skipped on replay, preserving saved translations.
        $rows = $root->translations()->where('id', '>', $this->afterId)->orderBy('id')->limit($this->chunkSize)->get();
        if ($target->id !== $root->id) {
            $ids = [];
            foreach ($rows as $row) $ids[] = $row->id;
            $this->massCreateEloquentTranslations($ids, $target, $root);
        }
        $next = clone $this;
        $last = $rows->last();
        if ($last !== null && $rows->count() === $this->chunkSize) {
            $next->afterId = $last->id;
        } else {
            $next->targetIndex++;
            $next->afterId = 0;
        }
        $this->finishChunk(isset($this->languageIds[$next->targetIndex]) ? $next : null);
    }

    public function estimatedJobs(): int
    {
        return count($this->languageIds) * $this->estimateChunks(Translation::query()->where('language_id', $this->languageId)->count());
    }
}
