<?php

namespace AnyMedia\Interpresso\Services;

use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Translation;

class ApproveLanguagesService
{
    public function approveChunk(Language $language, int $authUserId, int $afterId, int $chunkSize): ?int
    {
        // Stable id > afterId boundaries survive a crash after UPDATE. Only pending
        // approvals are changed, so replay cannot overwrite existing attribution.
        $rows = $language->translations()->where('id', '>', $afterId)->orderBy('id')->limit($chunkSize)->get();
        $pending = $rows->where('approved', false);
        if ($pending->isNotEmpty()) {
            Translation::query()->whereIn('id', $pending->modelKeys())->where('approved', false)
                ->update($this->approvedTranslationUpdateArray($authUserId));
            foreach ($pending as $translation) {
                $this->resetTranslationCache($translation);
            }
            Translation::invalidateCacheAfterWrite();
        }
        return $rows->count() === $chunkSize ? $rows->last()?->id : null;
    }

    public function approveLanguages(Language $language, int $authUserId): void {

        $language->translations()->where('approved', false)
            ->chunkById(100, function($translations) use ($authUserId) {
                Translation::query()->whereIn('id', $translations->pluck('id')->all())->update($this->approvedTranslationUpdateArray($authUserId));
                foreach ($translations as $translation) {
                    $this->resetTranslationCache($translation);
                }
                Translation::invalidateCacheAfterWrite();
            });
    }

    /**
     * @return array{approved: true, updated_translation: false, needs_translation: false, old_value: null, approved_by: int, previous_updated_by: null, previous_approved_by: null}
     */
    public function approvedTranslationUpdateArray(int $authUserId): array
    {
        return [
            'approved' => true,
            'updated_translation' => false,
            'needs_translation' => false,
            'old_value' => null,
            'approved_by' => $authUserId,
            'previous_updated_by' => null,
            'previous_approved_by' => null,
        ];
    }

    public function resetTranslationCache(Translation $translation): void
    {
        Translation::unsetCachedTranslation($translation->language_code, $translation->group ?? null, $translation->namespace ?? null);
//        Translation::getCachedTranslations($translation->language_code, $translation->group ?? null, $translation->namespace ?? null);
    }
}
