<?php

namespace AnyMedia\Interpresso\Jobs;

use AnyMedia\Interpresso\Jobs\Job\ChunkedJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\MissingTranslationService;

class FindMissingTranslationsJob extends ChunkedJob
{
    /**
     * @return void
     * @throws \AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException
     */
    public function handle(): void
    {
        if (!$this->startChunk()) return;
        resolve(MissingTranslationService::class)->findMissingTranslations($this->batch());
        $this->finishChunk();
    }

    public function estimatedJobs(): int
    {
        $languages = Language::all();
        $root = $languages->firstWhere('code', config('app.locale')) ?? $languages->first();
        if ($root === null) return 1;
        $total = 1;
        foreach ($languages as $language) {
            if ($language->id !== $root->id) {
                $total += (new FindMissingTranslationsByLanguage([$language->id], $root->id))->estimatedJobs();
            }
        }
        return $total;
    }
}
