<?php

namespace AnyMedia\Interpresso\Services;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use AnyMedia\Interpresso\Exceptions\ExportTranslationException;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;
use AnyMedia\Interpresso\Services\Traits\CanExportTranslation;

class ExportTranslationService
{
    use CanExportTranslation;

    /** Process at most $chunkSize source IDs; return the next cursor for a full slice. */
    public function exportChunk(Language $language, int $afterId, int $chunkSize, bool $onlyModels = false, bool $force = false): ?int
    {
        // Select by stable IDs, not the mutable exported/approved flags: replaying
        // id > afterId selects the same slice even after some writes committed.
        $rows = Translation::query()->where('language_id', $language->id)
            ->where('id', '>', $afterId)
            ->when($onlyModels, fn ($query) => $query->where('type', 'model'))
            ->orderBy('id')->limit($chunkSize)->get();
        try {
            $files = [];
            foreach ($rows as $row) {
                if (!$row->approved || $row->updated_translation || (!$force && $row->exported)) continue;
                if ($row->type === 'model') {
                    // Assign the language key, never append: repeating a model write is safe.
                    $this->updateModelTranslation($row, $language->code);
                    continue;
                }
                $namespace = $row->namespace ?? throw new \DomainException('Cannot export translation with a null namespace.');
                $group = $row->group ?? throw new \DomainException('Cannot export translation with a null group.');
                $relative = $language->code . ($row->type === 'json' ? '' : '/' . $group) . '.' . $row->type;
                $path = App::langPath(($row->is_vendor ? 'vendor/' . $namespace . '/' : '') . $relative);
                $files[$path]['type'] = $row->type;
                $files[$path]['values'][$row->key] = $row->value;
                $files[$path]['ids'][] = $row->id;
            }
            foreach ($files as $path => $file) {
                // Atomic replacement precedes the exported flag. A crash in between
                // repeats the same keyed merge; it cannot truncate or duplicate keys.
                $this->updateFileContent($file['values'], $path, $file['type']);
                Translation::query()->whereIn('id', $file['ids'])->update(['exported' => true]);
                Translation::invalidateCacheAfterWrite();
            }
        } catch (\Exception $e) {
            throw new ExportTranslationException($e->getMessage(), __('interpresso::exceptions.export_language_error', ['language' => $language->native_name]), 0);
        }
        return $rows->count() === $chunkSize ? $rows->last()?->id : null;
    }

    /**
     * @var null|Batch
     */
    protected null|Batch $batch = null;

    /**
     * @var string
     */
    protected string $tempLangFolder = 'temp-language-dir';

    /**
     * @var bool
     */
    protected bool $forceExportAll = false;

    public function exportTranslationsOnOtherHosts(): void
    {
        if (!Setting::multiHostEnabled()) {
            return;
        }

        $hosts = array_values(array_filter(array_map('trim', explode(',', Setting::getDomains() ?? ''))));
        if ($hosts === []) {
            return;
        }

        $hosts = array_values(array_diff($hosts, [request()->getSchemeAndHttpHost()]));
        if ($hosts === []) {
            return;
        }

        $path = route('interpresso.api.force-export', [], false);

        foreach($hosts as $host) {
            $response = Http::post($host . $path, ['api_key' => config('interpresso.api_shared_api_key')]);
            if($response->ok()) {
                $payload = $response->json();
                if (!is_array($payload)) {
                    throw new \TypeError('Export response must be a JSON object.');
                }
                $message = $payload['message'];
            } else {
                $message = __('interpresso::translations.export_on_other_host_start_failed', ['host' => $host]);
                $response->throw();
            }
            Translator::query()->admin()->each(function (Translator $translator) use ($message) {
                if (!is_string($message) && !is_int($message) && !is_float($message) && !is_bool($message) && !$message instanceof \Stringable) {
                    throw new \TypeError('Export response message must be a string.');
                }
                // FlashMessage's string parameter already coerces scalar messages.
                $message = (string) $message;
                $translator->notify(new FlashMessage( $message));
            });
        }
    }

    /**
     * @param Language $language
     * @param Batch|null $batch
     * @param bool $exportOnlyModels
     * @return void
     * @throws ExportTranslationException
     */
    public function forceExportTranslationForLanguage(Language $language, null|Batch $batch = null, bool $exportOnlyModels = false): void
    {
        $this->forceExportAll = true;
        $this->exportTranslationForLanguage($language, $batch, $exportOnlyModels);
    }


    /**p
     * @param Language $language
     * @param Batch|null $batch
     * @param bool $exportOnlyModels
     * @return void
     * @throws ExportTranslationException
     */
    public function exportTranslationForLanguage(Language $language, null|Batch $batch = null, bool $exportOnlyModels = false): void
    {
        try {
            if (!$exportOnlyModels) {
                $this->exportFileTranslationForLanguage($language, $batch);
            }
            $this->exportModelTranslationForLanguage($language, $batch);
        } catch(\Exception $e) {
            throw new ExportTranslationException($e->getMessage(), __('interpresso::exceptions.export_language_error', ['language' => $language->native_name]), 0);
        }

    }

    /**
     * @param Language $language
     * @param Batch|null $batch
     * @return void
     * @throws ExportTranslationException
     */
    protected function exportFileTranslationForLanguage(Language $language, null|Batch $batch = null): void
    {
        if ($batch) {
            $this->batch = $batch;
        }
        $tempDirectory = App::langPath($this->tempLangFolder);
        $tempLangDirectory = $tempDirectory . '/' . $language->code;
        $languageDirectory = App::langPath($language->code);
        File::copyDirectory($languageDirectory, $tempLangDirectory);
        try {
            Translation::query()
                ->select('namespace', 'group', 'is_vendor', 'type')
                ->where('language_id', $language->id)
                ->where('type', '!=', 'model')
                ->isUpdated(false)
                ->approved()
                ->when(!$this->forceExportAll, function($query) {
                    $query->exported(false);
                })
                ->groupBy('namespace', 'group', 'is_vendor', 'type')
                ->orderBy('group')
                ->chunk(200, function ($translations) use ($language) {
                    foreach ($translations as $translation) {
                        $namespace = $translation->namespace ?? throw new \DomainException('Cannot export translation with a null namespace; use an empty string for application translations.');
                        $group = $translation->group ?? throw new \DomainException('Cannot export translation with a null group; use an empty string for JSON translations.');
                        if ($this->batch) {
                            $this->updateTranslation($translation->type, $language->code, $translation->is_vendor, $namespace, $group, $this->forceExportAll);

//                            $this->batch->add([new ExportUpdatedTranslation($translation->type, $language->code, $translation->is_vendor, $translation->namespace, $translation->group, $this->forceExportAll)]);
                        } else {
                            $this->updateTranslation($translation->type, $language->code, $translation->is_vendor, $namespace, $group, $this->forceExportAll);
                        }
                    }
                });
        } catch (\Exception $e) {
            Log::error('ExportTranslationService::exportFileTranslationForLanguage.', [
                'ErrorMessage' => $e->getMessage()
            ]);
            File::deleteDirectory($languageDirectory);
            File::copyDirectory($tempLangDirectory, $languageDirectory);
            File::deleteDirectory($tempDirectory);
            throw $e;
        }
        File::deleteDirectory($tempDirectory);
    }


    /**
     * @param Language $language
     * @param Batch|null $batch
     * @return void
     * @throws ExportTranslationException
     */
    public function exportModelTranslationForLanguage(Language $language, null|Batch $batch = null): void
    {
        if ($batch) {
            $this->batch = $batch;
        }
        try {
            Translation::query()
                ->select('id', 'namespace', 'group', 'key', 'value')
                ->where('language_id', $language->id)
                ->where('type', '=', 'model')
                ->isUpdated(false)
                ->approved()
                ->when(!$this->forceExportAll, function($query) {
                    $query->exported(false);
                })
                ->chunkById(200, function ($translations) use ($language) {
                    foreach ($translations as $translation) {
                        if ($this->batch) {
                            $this->updateModelTranslation($translation, $language->code);
//                            $this->batch->add(new ExportUpdatedModelTranslation($translation->id, $language->code));
                        } else {
                            $this->updateModelTranslation($translation, $language->code);
                        }
                    }
                });
        } catch(\Exception $e) {
            throw $e;
        }
    }
}
