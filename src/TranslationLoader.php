<?php

namespace AnyMedia\Interpresso;

use Illuminate\Translation\FileLoader;
use Illuminate\Support\Arr;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;

class TranslationLoader extends FileLoader
{
    /**
     * Loads the translations from DB
     *
     * @param string $locale
     * @param string $group
     * @param string|null $namespace
     *
     * @return array<array-key, mixed> External translation file contents or stored lines; numeric keys are allowed.
     */
    public function load($locale,  $group, $namespace = null): array
    {
        if(!Setting::getCached()->import_vendor && $namespace && $namespace !== '*') {
            /** @var array<array-key, mixed> $lines FileLoader returns arbitrary PHP/JSON translation file contents. */
            $lines = parent::load($locale, $group, $namespace);
            return $lines;
        }
        $lines = Translation::getCachedTranslations($locale, $group, $namespace);
        if ($group === 'validation' && ($namespace === null || $namespace === '*')) {
            // Fresh DB installs have no imported validation messages. Keep
            // Laravel/host messages available, with reviewed DB overrides.
            return array_replace_recursive(parent::load($locale, $group, $namespace), Arr::undot($lines));
        }
        return $lines;
    }
}
