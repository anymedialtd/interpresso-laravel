<?php

namespace AnyMedia\Interpresso\Services;

use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use AnyMedia\Interpresso\Exceptions\ImportTranslationsException;
use AnyMedia\Interpresso\Helpers\LanguageHelper;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Services\Traits\CanCreateTranslation;
use Symfony\Component\Finder\SplFileInfo;

class ImportTranslationService
{
    use CanCreateTranslation;

    /** @return list<ImportSource> */
    public function sources(): array
    {
        $settings = Setting::getCached();
        $languages = Language::query()->when($settings->import_only_from_root_language,
            fn ($query) => $query->where('code', config('app.locale')))->orderBy('id')->get();
        $roots = [['path' => App::langPath(), 'namespace' => '', 'vendor' => false]];
        if ($settings->import_vendor) {
            foreach (Lang::getLoader()->namespaces() as $namespace => $directory) {
                if (!is_string($directory)) throw new \TypeError('Translation namespace directories must be strings.');
                // Preserve precedence: published vendor entries are imported first.
                $roots[] = ['path' => App::langPath('vendor/' . $namespace), 'namespace' => $namespace, 'vendor' => true];
                $roots[] = ['path' => $directory, 'namespace' => $namespace, 'vendor' => true];
            }
        }
        $sources = [];
        foreach ($roots as $root) {
            foreach ($languages as $language) {
                $json = $root['path'] . '/' . $language->code . '.json';
                if (File::isFile($json)) {
                    $sources[] = new ImportSource($language->id, $language->code, 'json', $json, $root['namespace'], '', $root['vendor']);
                }
                $directory = $root['path'] . '/' . $language->code;
                if (!File::isDirectory($directory)) continue;
                foreach (File::allFiles($directory) as $file) {
                    $type = $file->getExtension();
                    if (!in_array($type, ['php', 'json'], true)) continue;
                    $group = $type === 'php' ? substr($file->getRelativePathname(), 0, -4) : '';
                    $sources[] = new ImportSource($language->id, $language->code, $type, $file->getPathname(), $root['namespace'], $group, $root['vendor']);
                }
            }
        }
        $models = config('interpresso.translatable_models');
        if (!is_array($models)) throw new \TypeError('interpresso.translatable_models must be an array of model class names.');
        foreach ($models as $class) {
            if (!is_string($class)) throw new \TypeError('Translatable model class names must be strings.');
            $model = app($class);
            if (!$model instanceof Model) throw new \TypeError('Translatable models must be Eloquent models.');
            $columns = property_exists($model, 'translatable') ? $model->translatable : $model->getAttribute('translatable');
            if (!$columns) continue;
            if (!is_array($columns)) throw new \TypeError('Translatable columns must be an array of strings.');
            foreach ($languages as $language) {
                foreach ($columns as $column) {
                    if (!is_string($column)) throw new \TypeError('Translatable column names must be strings.');
                    $sources[] = new ImportSource($language->id, $language->code, 'model', $class, $class, $column);
                }
            }
        }
        return $sources;
    }

    public function importChunk(ImportSource $source, int|string|null $afterId, int $chunkSize): int|string|null
    {
        if ($source->type === 'model') {
            $model = $source->model();
            $key = $model->getKeyName();
            $rows = $model->getConnection()->table($model->getTable())->select($key, $source->group)
                ->when($afterId !== null, fn ($query) => $query->where($key, '>', $afterId))
                ->orderBy($key)->limit($chunkSize)->get();
            $content = [];
            $lastId = null;
            foreach ($rows as $row) {
                $lastId = $row->$key;
                if (!is_int($lastId) && !is_string($lastId)) throw new \TypeError('Translatable model keys must be integers or strings.');
                $data = $row->{$source->group};
                if (is_string($data)) $data = json_decode($data, true);
                if (is_object($data)) $data = (array) $data;
                if (is_array($data) && isset($data[$source->languageCode])) {
                    $value = $data[$source->languageCode];
                    if (!is_scalar($value)) throw new \TypeError('Model translation values must be strings.');
                    $content[$source->group . '.' . $lastId] = (string) $value;
                }
            }
            // id > afterId reads stable source rows. Insert-only shared identifiers
            // skip previously imported values on replay, including administrator edits.
            $this->massCreateTranslations($content, 'model', $source->languageId, $source->languageCode, '', $source->path, false);
            return $rows->count() === $chunkSize ? $lastId : null;
        }

        try {
            $fingerprint = hash_file('sha256', $source->path);
            if ($fingerprint === false) throw new \RuntimeException('Cannot read import source.');
            if ($source->fingerprint !== null && $source->fingerprint !== $fingerprint) {
                throw new \RuntimeException('Import source changed between chunks; start a new import.');
            }
            $source->fingerprint = $fingerprint;
            $content = $source->type === 'php' ? File::getRequire($source->path) : json_decode(File::get($source->path), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($content)) throw new \TypeError('Translation file content must be an array.');
            $content = resolve(LanguageHelper::class)->array_convert_keys_to_dot_notation($content);
            if (hash_file('sha256', $source->path) !== $fingerprint) throw new \RuntimeException('Import source changed while reading.');
            // Files have no database IDs. Their immutable flattened entry ordinals
            // are the cursor IDs; fingerprint checking prevents reordered input from
            // skipping entries. PHP/JSON still require parsing one source file at a time.
            $offset = is_int($afterId) ? $afterId : 0;
            $slice = array_slice($content, $offset, $chunkSize, true);
            foreach ($slice as $key => $value) {
                if (!is_scalar($value)) throw new \TypeError('Translation values must be strings.');
                $slice[$key] = (string) $value;
            }
            // Import is insert-only, so replay cannot duplicate keys or reset edits.
            $this->massCreateTranslations($slice, $source->type, $source->languageId, $source->languageCode, $source->namespace, $source->group, $source->isVendor);
            return count($slice) === $chunkSize ? $offset + count($slice) : null;
        } catch (\Throwable $e) {
            throw new ImportTranslationsException($e->getMessage(), __('interpresso::exceptions.invalid_file_error', ['relativePathname' => $source->path]), 0);
        }
    }

    protected null|Batch $batch = null;
    protected ?ProcessLock $processLock = null;

    protected string $root;

    protected string $namespace = '';

    /**
     * @var Collection<int, Language>
     */
    protected Collection $languages;

    protected Language $language;

    protected \SplFileInfo $file;
    protected bool $isVendor = false;
    protected string $languagePlaceholder = '{language}';

    /**
     * @throws ImportTranslationsException
     */
    public function importTranslations(null|Batch $batch = null, ?ProcessLock $lock = null): void
    {
        $this->processLock = $lock;
        if ($batch) {
            $this->batch = $batch;
        }

        /** @var Collection<int, Language> $languages */
        $languages = Language::query()
            ->when(Setting::getCached()->import_only_from_root_language, function($query) {
                $query->where('code', config('app.locale'));
            })->get();
        $this->languages = $languages;

        $this->createMissingDirectory(App::langPath());
        $this->createMissingDirectory(App::langPath('vendor'));

        $this->root = App::langPath();
        $this->importFromRoot();

        $this->importVendorTranslations();

        // Imports model translations
        $this->importModelTranslations();
    }

    protected function importModelTranslations(): void
    {
        $models = config('interpresso.translatable_models');
        if (!is_array($models)) {
            throw new \TypeError('interpresso.translatable_models must be an array of model class names.');
        }

        foreach($models as $modelClass) {
            if (!is_string($modelClass)) {
                throw new \TypeError('Translatable model class names must be strings.');
            }
            $modelInstance = app($modelClass);
            if (!$modelInstance instanceof Model) {
                throw new \TypeError('Translatable models must be Eloquent models.');
            }
            $tableId = $modelInstance->getKeyName();
            DB::table($modelInstance->getTable())->chunkById(300,
                function($models) use( $modelClass, $modelInstance, $tableId) {
                    $this->processLock?->refresh();
                    $content = [];
                    foreach($this->languages as $language) {
                        /** @var Language $language */
                        $languageCode = $language->code;
                        $content[$languageCode] = [];
                        foreach($models as $model) {
                            $columns = property_exists($modelInstance, 'translatable')
                                ? $modelInstance->translatable
                                : $modelInstance->getAttribute('translatable');
                            if(!$columns) continue;
                            if (!is_array($columns)) {
                                throw new \TypeError('Translatable columns must be an array of strings.');
                            }
                            foreach($columns as $column) {
                                if (!is_string($column)) {
                                    throw new \TypeError('Translatable column names must be strings.');
                                }
                                $data = $model->$column;
                                if(is_string($data)) $data = json_decode($data, true);
                                if(is_object($data)) $data = (array) $data;
                                if(is_array($data) && isset($data[$languageCode])) {
                                    $modelId = $model->$tableId;
                                    if (!is_int($modelId) && !is_string($modelId)) {
                                        throw new \TypeError('Translatable model keys must be integers or strings.');
                                    }
                                    $value = $data[$languageCode];
                                    if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value) && !$value instanceof \Stringable) {
                                        throw new \TypeError('Model translation values must be strings.');
                                    }
                                    // getTranslationArray's string parameter already coerces these values.
                                    $content[$languageCode][$column . '.' . $modelId] = (string) $value;
                                }
                            }
                        }
                        if ($this->batch) {
                            $this->massCreateTranslations($content[$languageCode],  'model', $language->id, $language->code, $this->namespace,  $modelClass, $this->isVendor);

                            //Inefficient can be removed
//                            $this->batch->add(new MassCreateTranslationsJob($content[$languageCode],'model', $language->id, $language->code, $this->namespace,  $modelClass, $this->isVendor));
                        } else {
                            $this->massCreateTranslations($content[$languageCode],  'model', $language->id, $language->code, $this->namespace,  $modelClass, $this->isVendor);
                        }
                    }
                }
            );

        }
    }

    /**
     * @return void
     * @throws ImportTranslationsException
     */
    protected function importVendorTranslations(): void
    {
        if(Setting::getCached()->import_vendor) {
            $loader = Lang::getLoader();
            $this->isVendor = true;
            foreach ($loader->namespaces() as $namespace => $directory) {
                $this->namespace = $namespace;
                $publishedVendorDirectory = App::langPath('vendor/' . $this->namespace);
                $this->createMissingDirectory($publishedVendorDirectory);
                // Look first if there are published lang files
                $this->root = $publishedVendorDirectory;
                $this->importFromRoot();
                // Look for not published lang files
                if (!is_string($directory)) {
                    throw new \TypeError('Translation namespace directories must be strings.');
                }
                $this->root = $directory;
                $this->importFromRoot();
            };
        }
    }


    /**
     * @return void
     * @throws ImportTranslationsException
     */
    protected function importFromRoot(): void
    {
        // Get files from root directory
        $rootJsonFiles = collect(File::files($this->root))->mapWithKeys(function (SplFileInfo $file) {
            return [$file->getFilename() => $file];
        })->all();

        // Loop through languages in directory
        foreach ($this->languages as $language) {
            /** @var Language $language */
            $this->language = $language;
            if ($this->isVendor) {
                $this->createMissingDirectory(App::langPath('vendor/' . $this->namespace) . '/' . $this->language->code);
            } else {
                $this->createMissingDirectory($this->root . '/' . $this->language->code);
            }

            // Handles JSON Language file in root directory
            if (isset($rootJsonFiles[$this->language->code . '.json'])) {
                $this->generateContent($this->root, $rootJsonFiles[$this->language->code . '.json']);
            }

            // Handles files in language subdirectory
            if(File::exists($this->root . '/' . $this->language->code)) {
                foreach (File::allFiles($this->root . '/' . $this->language->code) as $file) {
                    $this->generateContent(str_replace('/src/../', '/', $this->root), $file);
                }
            }
        }
    }

    /**
     * @throws ImportTranslationsException
     */
    protected function generateContent(string $root, \SplFileInfo $file): void
    {
        $this->processLock?->refresh();
        $relativePathname = $file->getFilename();

        try {
            $languageHelper = resolve(LanguageHelper::class);
            if(File::exists($file->getRealPath())) {
                $relativePathname = str_replace($root, '', $file->getRealPath());
                $relativePath = File::dirname($relativePathname);
                $type = $file->getExtension();
                if (!in_array($type, ['json', 'php'])) {
                    Log::error('File (' . $relativePathname . ') has an invalid file extension. File extension must be php or json. Remove the file or change the extension.');
                }

                if ($type == 'json') {
                    $group = '';
                    $content = json_decode(File::get($file->getRealPath()), true);
                } else {
                    $offset = strlen($this->language->code) + 2;
                    $group = str_replace('.' . $type, '', substr($relativePathname, $offset));
                    $content = require($file->getRealPath());
                }

                if (!is_array($content)) {
                    if ($type == 'php') {
                        Log::error('File (' . $relativePathname . ') has no valid php array, Please check the file in the filesystem.');
                    } else {
                        Log::error('File (' . $relativePathname . ') has no valid JSON string, Please check the file in the filesystem.');
                    }

                    return;
                }

                if (count($content) > 0) {
                    $content = $languageHelper->array_convert_keys_to_dot_notation($content);
                    foreach ($content as $key => $value) {
                        if (!is_string($value) && !is_int($value) && !is_float($value) && !is_bool($value)) {
                            throw new \TypeError('Translation values must be strings.');
                        }
                        // getTranslationArray's string parameter already coerces scalar leaves.
                        $content[$key] = (string) $value;
                    }
                    if ($this->batch) {
                        $this->massCreateTranslations($content, $type, $this->language->id, $this->language->code, $this->namespace, $group, $this->isVendor);
                        // Inefficient can be removed
//                        $this->batch->add(new MassCreateTranslationsJob($content, $type, $this->language->id, $this->language->code, $this->namespace, $group, $this->isVendor));
                    } else {
                        $this->massCreateTranslations($content, $type, $this->language->id, $this->language->code, $this->namespace, $group, $this->isVendor);
                    }
                }
            }
        } catch (\Throwable $e) {
            throw new ImportTranslationsException($e->getMessage(), __('interpresso::exceptions.invalid_file_error', ['relativePathname' => $relativePathname]), 0);
        }
    }

    /**
     * @param string $path
     * @return void
     */
    protected function createMissingDirectory(string $path): void
    {
        if(!Setting::getCached()->db_loader) {
            if (!File::exists($path)) {
                File::makeDirectory($path);
            }
        }
    }

}
