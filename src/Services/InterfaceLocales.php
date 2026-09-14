<?php

namespace AnyMedia\Interpresso\Services;

use Illuminate\Filesystem\Filesystem;

class InterfaceLocales
{
    /** @var list<string>|null */
    private ?array $locales = null;

    public function __construct(
        private Filesystem $files,
        private string $path = __DIR__ . '/../../lang',
    ) {}

    /** @return list<string> */
    public function codes(): array
    {
        if ($this->locales === null) {
            $this->locales = [];
            foreach ($this->files->directories($this->path) as $directory) {
                if (!is_string($directory)) {
                    continue;
                }
                $locale = basename($directory);
                if (preg_match('/\A[a-zA-Z]{2,3}(?:[_-][a-zA-Z0-9]{2,8})*\z/', $locale) === 1
                    && $this->files->glob($directory . '/*.php')) {
                    $this->locales[] = $locale;
                }
            }
            sort($this->locales);
        }

        return $this->locales;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $options = [];
        foreach ($this->codes() as $locale) {
            $name = app('translator')->get('interpresso::global.locale_name', [], $locale, false);
            $options[$locale] = is_string($name) && $name !== 'interpresso::global.locale_name' ? $name : $locale;
        }

        return $options;
    }

    public function supports(mixed $locale): bool
    {
        return is_string($locale) && in_array($locale, $this->codes(), true);
    }

    public function resolve(mixed ...$preferences): string
    {
        foreach ($preferences as $locale) {
            if (is_string($locale) && $this->supports($locale)) {
                return $locale;
            }
        }

        // Even an unsupported host locale must produce a complete package UI.
        return $this->supports('en') ? 'en' : ($this->codes()[0] ?? throw new \LogicException('No Interpresso interface locales are available.'));
    }
}
