<?php

namespace AnyMedia\Interpresso\Services\Traits;

use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use AnyMedia\Interpresso\Exceptions\MassCreateTranslationsException;
use AnyMedia\Interpresso\Jobs\MassCreateEloquentTranslationsJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Services\OpenAITranslationService;

/**
 * @phpstan-type TranslationAttributes array{
 *     language_id: int,
 *     language_code: string,
 *     shared_identifier: string,
 *     type: string,
 *     namespace: string,
 *     is_vendor: bool,
 *     group: string,
 *     key: string,
 *     value: string,
 *     approved: bool,
 *     needs_translation: bool,
 *     updated_translation: bool,
 *     created_at: CarbonInterface,
 *     updated_at: CarbonInterface,
 *     exported?: bool
 * }
 * @phpstan-type StoredTranslation array{
 *     id: int,
 *     language_id: int,
 *     language_code: string,
 *     shared_identifier: string,
 *     type: string,
 *     namespace: string|null,
 *     is_vendor: bool,
 *     group: string|null,
 *     key: string,
 *     value: string|null,
 *     old_value: string|null,
 *     approved: bool,
 *     needs_translation: bool,
 *     updated_translation: bool,
 *     updated_by: int|null,
 *     previous_updated_by: int|null,
 *     approved_by: int|null,
 *     previous_approved_by: int|null,
 *     exported: bool,
 *     created_at: string|null,
 *     updated_at: string|null
 * }
 * The backup shape describes validated scalar fields. Carbon can customize the
 * JSON payloads of timestamp fields, which are passed through unchanged.
 *
 * @phpstan-type SerializedTranslation array{
 *     language_id: int,
 *     language_code: string,
 *     shared_identifier: string,
 *     type: string,
 *     namespace: string,
 *     is_vendor: bool,
 *     group: string,
 *     key: string,
 *     value: string,
 *     approved: bool,
 *     needs_translation: bool,
 *     updated_translation: bool,
 *     exported: bool
 * }
 */
trait CanCreateTranslation
{
    /**
     * @var Collection<int, Language>
     */
    protected Collection $missingLanguages;

    /**
     * @param Collection<int, Language> $languages
     * @param Language $rootLanguage
     * @return void
     * @throws MassCreateTranslationsException
     */
    public function findMissingTranslationsByLanguage(Collection $languages, Language $rootLanguage, ?Batch $batch = null): void
    {
        /** @var Collection<int, Language> $missingLanguages */
        $missingLanguages = $languages->reject(
            fn (Language $language): bool => $language->id === $rootLanguage->id
        );
        $this->missingLanguages = $missingLanguages;

        Translation::query()
            ->where('language_code', $rootLanguage->code)
            ->chunkById(100,
                /** @param Collection<int, Translation> $records */
                function (Collection $records) use ($rootLanguage, $batch): void {
                    foreach ($this->missingLanguages as $language) {
                        /** @var Language $language */

                        // Get array of all identifier
                        /** @var list<string> $identifierArray */
                        $identifierArray = $records->pluck('shared_identifier')->all();

                        // Get array of language identifier found
                        /** @var list<string> $identifierArrayTwo */
                        $identifierArrayTwo = Translation::query()
                            ->where('language_id', $language->id)
                            ->whereIn('shared_identifier', $identifierArray)->pluck('shared_identifier')->all();

                        // Get missing identifier for language
                        $missingIdentifier = array_diff($identifierArray, $identifierArrayTwo);

                        $fromLanguageCode = $rootLanguage->code;
                        /** @var int $chunkSize Configured in config/interpresso.php. */
                        $chunkSize = Setting::getCached()->enable_open_ai_translations ? config('interpresso.max_open_ai_missing_trans') : 400;
                        Translation::query()
                            ->whereIn('shared_identifier', $missingIdentifier)
                            ->where('language_code', $fromLanguageCode)
                            ->chunkById($chunkSize,
                                /** @param Collection<int, Translation> $translations */
                                function (Collection $translations) use ($language, $batch, $rootLanguage): void {
                                    /** @var list<int> $translationIds */
                                    $translationIds = $translations->pluck('id')->all();
                                    if($batch) {
                                        $batch->add(new MassCreateEloquentTranslationsJob($translationIds, $language->id, $rootLanguage->id));
                                    } else {
                                        $this->massCreateEloquentTranslations($translationIds, $language, $rootLanguage);
                                    }
                                }
                            );
                    }
                }
            );

    }

    /**
     * @param array<int|string, string> $content Flattened translation keys and their text.
     * @throws MassCreateTranslationsException
     */
    protected function massCreateTranslations(array $content, string $type, int $languageId, string $languageCode, string $namespace, string $group, bool $isVendor): void
    {
        try {
            $translationsArray = [];
            if($type == 'model') {
                $namespace = $group;
            }
            foreach ($content as $key => $value) {
                // PHP turns numeric array keys into ints; the string parameters already coerced them.
                $key = (string) $key;
                $sharedIdentifier = $type . $namespace . $group . $key;
                $sharedIdentifier = base64_encode($sharedIdentifier);

                // Model translations carry their group in the key as "group.key".
                // Derive it into a local, never into $group: reassigning the
                // parameter leaked the previous iteration's group into the next
                // shared identifier, which is the value used to link a translation
                // across languages. array_pad guards a key with no separator, which
                // previously raised an undefined-offset error.
                $itemGroup = $group;
                if($type == 'model') {
                    [$itemGroup, $key] = array_pad(explode('.', $key, 2), 2, '');
                }
                $translationsArray[$sharedIdentifier] = $this->getTranslationArray($languageId, $languageCode, $sharedIdentifier, $type, $key, $value, $namespace, $itemGroup, $isVendor);
            }

            $existingKeys = Translation::select('shared_identifier', 'language_code')
                ->where('language_code', $languageCode)
                ->whereIn('shared_identifier', array_keys($translationsArray))
                ->pluck('shared_identifier')->all();

            $translationsArray = array_filter($translationsArray, function($translation, $key) use ($existingKeys) {
                return !in_array($key, $existingKeys);
            }, ARRAY_FILTER_USE_BOTH);

            if (count($translationsArray) > 0) {
                $this->massInsertTranslations($translationsArray);
            }
            Translation::unsetCachedTranslation($languageCode, $group, $namespace);
        } catch (\Exception|MassCreateTranslationsException $e) {
            if ($e::class == MassCreateTranslationsException::class) {
                throw $e;
            } else {
                $relativePath = $type == 'json' ? $languageCode : $languageCode . '/' . $group;
                if($isVendor) {
                    $relativePathname = App::langPath('vendor/' . $namespace . '/' . $relativePath . '.' . $type);
                } else {
                    $relativePathname = App::langPath($relativePath . '.' . $type);
                }
                Log::error('Something went wrong while mass creating translations.', ['relativePathname' => $relativePathname, 'array' => $translationsArray]);
                throw new MassCreateTranslationsException($e->getMessage(), __('interpresso::exceptions.mass_create_fails', ['relativePathname' => $relativePathname]));
            }
        }
    }

    /**
     * @param list<int> $translationIds
     * @param Language $language
     * @param Language $rootLanguage
     * @return void
     * @throws MassCreateTranslationsException
     */
    protected function massCreateEloquentTranslations(array $translationIds, Language $language, Language $rootLanguage): void
    {
        try {
            $translationsArray = [];
            $openTranslateService = resolve(OpenAITranslationService::class);
            /** @var list<StoredTranslation> $translations Eloquent attributes after casts and date serialization. */
            $translations = Translation::whereIn('id', $translationIds)->get()->toArray();
            foreach ($translations as $translation) {
                if (!$this->translationExists($translation['shared_identifier'], $language->code)) {
                    $generatedTranslation = $this->getTranslationArray(
                        $language->id,
                        $language->code,
                        $translation['shared_identifier'],
                        $translation['type'],
                        $translation['key'],
                        $translation['value'] ?? throw new \DomainException('Cannot copy translation with a null value.'),
                        $translation['namespace'] ?? throw new \DomainException('Cannot copy translation with a null namespace; use an empty string for application translations.'),
                        $translation['group'] ?? throw new \DomainException('Cannot copy translation with a null group; use an empty string for JSON translations.'),
                        $translation['is_vendor'],
                        false,
                        true
                    );
                    $generatedTranslation['exported'] = false;
                    $translationsArray[] = $generatedTranslation;
                }
            }
            if ($translationsArray === []) return;
            // Retrying after insertion must not call AI again or replace saved values.
            // A crash after the external response but before insertion can repeat the
            // API request (and its cost); that provider call is not transactional.
            try {
                $encodedTranslations = json_encode($translationsArray);
                // json_decode previously coerced false to an empty string on encoding failure.
                $tempTranslationsArray = json_decode($encodedTranslations === false ? '' : $encodedTranslations, true);
                if (!$this->isTranslationBackup($tempTranslationsArray)) {
                    throw new \TypeError('Translation backup contains invalid attributes.');
                }

                // Get Open Api translated array
                $translatedArrayResult = [];
                $translatedArrayResult = $openTranslateService->translateArray(
                    $rootLanguage,
                    $language,
                    collect($translationsArray)->mapWithKeys(function($translation, $index) {
                        return ['t_' . $index => $translation['value']];
                    })->all()
                );
                if(empty($translatedArrayResult)) throw new \Exception('Open AI returned null value');

                $translationsArray = collect($translationsArray)->map(function ($translation, $index) use (
                    $translatedArrayResult,
                    $openTranslateService,
                    $rootLanguage,
                    $language
                ) {
                    if(isset($translatedArrayResult['t_' . $index])) {
                        $translation['value'] = $translatedArrayResult['t_' . $index];
                    } else {
                        $res = $openTranslateService->translateString($rootLanguage, $language,  $translation['value']);
                        if(!empty($res)) {
                            $translation['value'] = $res;
                        }
                    }
                    return $translation;
                })->all();
            } catch(\Exception $e) {
                Log::warning('CanCreateTranslation::massCreateEloquentTranslations() ArrayTranslations failed -> ' . $e->getMessage(), [
                    'translationsArray' => $translationsArray,
                    'translatedArrayResult' => $translatedArrayResult
                ]);
                $translationsArray = $tempTranslationsArray;

                    $translationsArray = collect($translationsArray)->map(function ($translation, $index) use ($openTranslateService, $rootLanguage,
                        $language) {
                        $tempValue = $translation['value'];
                        try {
                            $translation['value'] = $openTranslateService->translateString($rootLanguage, $language,  $translation['value']);
                            return $translation;
                        } catch(\Exception $e) {
                            Log::warning('CanCreateTranslation::massCreateEloquentTranslations() StringTranslations failed -> ' . $e->getMessage(), [
                                'value' => $translation['value'],
                            ]);
                            $translation['value'] = $tempValue;
                            return $translation;
                        }
                    })->all();

            }

            $this->massInsertTranslations($translationsArray);
        } catch (\Exception|MassCreateTranslationsException $e) {
            $errorId = Str::random();
            Log::error('Something went wrong while mass creating eloquent translations: ', [
                'error_id' => $errorId,
                'message' => $e->getMessage(),
                'fromLanguage' => $rootLanguage->code,
                'toLanguage' => $language->code,
                'shared_identifier' => collect($translations)->pluck('shared_identifier')->all()
            ]);
            throw new MassCreateTranslationsException($e->getMessage(), __('interpresso::exceptions.mass_create_eloquent_fails', ['errorId' => $errorId]));
        }

    }

    /**
     * @return TranslationAttributes|array{}
     */
    protected function getNewTranslation(int $languageId, string $languageCode, string $sharedIdentifier, string $type, string $key, string $value, string $namespace, string $group, bool $isVendor): array
    {
        if (!$this->translationExists($sharedIdentifier, $languageCode)) {
            return $this->getTranslationArray($languageId, $languageCode, $sharedIdentifier, $type, $key, $value, $namespace, $group, $isVendor);
        }
        return [];
    }

    /**
     * @return TranslationAttributes
     */
    protected function getTranslationArray(int $languageId, string $languageCode, string $sharedIdentifier, string $type, string $key, string $value, string $namespace, string $group, bool $isVendor, bool $approved = true, ?bool $needsTranslation = null): array
    {
        return [
            'language_id' => $languageId,
            'language_code' => $languageCode,
            'shared_identifier' => $sharedIdentifier,
            'type' => $type,
            'namespace' => $namespace,
            'is_vendor' => $isVendor,
            'group' => $group,
            'key' => $key,
            'value' => $value,
            'approved' => $approved,
            'needs_translation' => ($needsTranslation !== null) ? $needsTranslation : !$value,
            'updated_translation' => false,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Validate the decoded snapshot of newly generated translation attributes.
     * Null is retained on JSON failure for the existing empty-collection fallback.
     *
     * @phpstan-assert-if-true list<SerializedTranslation>|null $translations
     * @throws void
     */
    private function isTranslationBackup(mixed $translations): bool
    {
        if ($translations === null) {
            return true;
        }

        if (!is_array($translations) || !array_is_list($translations)) {
            return false;
        }

        foreach ($translations as $translation) {
            if (!is_array($translation)
                || !is_int($translation['language_id'] ?? null)
                || !is_string($translation['language_code'] ?? null)
                || !is_string($translation['shared_identifier'] ?? null)
                || !is_string($translation['type'] ?? null)
                || !is_string($translation['namespace'] ?? null)
                || !is_bool($translation['is_vendor'] ?? null)
                || !is_string($translation['group'] ?? null)
                || !is_string($translation['key'] ?? null)
                || !is_string($translation['value'] ?? null)
                || !is_bool($translation['approved'] ?? null)
                || !is_bool($translation['needs_translation'] ?? null)
                || !is_bool($translation['updated_translation'] ?? null)
                || !is_bool($translation['exported'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a record exists
     *
     * @param string $sharedIdentifier
     * @param string $languageCode
     * @return bool
     */
    protected function translationExists(
        string $sharedIdentifier,
        string $languageCode
    ): bool
    {
        return Translation::where(
            [
                ['shared_identifier', '=', $sharedIdentifier],
                ['language_code', '=', $languageCode]
            ],
        )->exists();
    }


    /**
     * @param array<int|string, array{
     *     language_id: int,
     *     language_code: string,
     *     shared_identifier: string,
     *     type: string,
     *     namespace: string,
     *     is_vendor: bool,
     *     group: string,
     *     key: string,
     *     value: string,
     *     approved: bool,
     *     needs_translation: bool,
     *     updated_translation: bool,
     *     exported?: bool
     * }|array{}> $translations
     * @return void
     * @throws MassCreateTranslationsException
     */
    protected function massInsertTranslations(array $translations): void
    {
        $translations = array_filter($translations);
        try {
            (new Translation())->getConnection()->transaction(function () use ($translations): void {
                $byLanguage = [];
                foreach ($translations as $translation) {
                    $byLanguage[$translation['language_id']][$translation['shared_identifier']] = $translation;
                }
                ksort($byLanguage);
                foreach ($byLanguage as $languageId => $rows) {
                    // A queue may redeliver a slice after it chained its successor.
                    // Serialize concurrent insert-only replays on the parent language,
                    // then check again inside the transaction before inserting.
                    Language::query()->whereKey($languageId)->lockForUpdate()->firstOrFail();
                    $existing = Translation::query()->where('language_id', $languageId)
                        ->whereIn('shared_identifier', array_keys($rows))->pluck('shared_identifier')->all();
                    foreach ($existing as $identifier) {
                        if (is_string($identifier)) unset($rows[$identifier]);
                    }
                    if ($rows !== []) {
                        Translation::insert(array_values($rows));
                        Translation::invalidateCacheAfterWrite();
                    }
                }
            });
        } catch (\Exception $e) {
            $errorId = Str::random();
            $firstTranslation = reset($translations);
            $languageCode = $firstTranslation === false ? '' : $firstTranslation['language_code'];
            Log::error('Couldn\'t mass insert translations language: ' . $languageCode, [
                'error_id' => $errorId,
                'shared_identifier' => collect($translations)->pluck('shared_identifier')->all()
            ]);
            throw new MassCreateTranslationsException($e->getMessage(), __('interpresso::exceptions.invalid_translation_array', ['errorId' => $errorId, 'languageCode' => $languageCode]));
        }
    }
}
