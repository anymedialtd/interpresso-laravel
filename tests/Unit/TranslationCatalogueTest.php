<?php

namespace AnyMedia\Interpresso\Tests\Unit;

use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TranslationCatalogueTest extends TestCase
{
    public static function locales(): array
    {
        return ['German' => ['de'], 'French' => ['fr'], 'Spanish' => ['es'], 'Italian' => ['it']];
    }

    private function catalogue(string $locale): array
    {
        $catalogue = [];
        foreach (glob(dirname(__DIR__, 2) . '/lang/' . $locale . '/*.php') as $path) {
            $catalogue[basename($path, '.php')] = require $path;
        }
        return $catalogue;
    }

    private function structure(array $values, string $prefix = ''): array
    {
        $structure = [];
        foreach ($values as $key => $value) {
            $path = $prefix . $key;
            $structure[$path] = is_array($value) ? 'array' : get_debug_type($value);
            if (is_array($value)) {
                $structure += $this->structure($value, $path . '.');
            }
        }
        ksort($structure);
        return $structure;
    }

    #[Test]
    #[DataProvider('locales')]
    public function every_locale_has_exactly_the_english_files_keys_and_structure(string $locale): void
    {
        $english = $this->structure($this->catalogue('en'));
        $translated = $this->structure($this->catalogue($locale));
        $missing = array_diff_key($english, $translated);
        $extra = array_diff_key($translated, $english);
        $changed = array_diff_assoc(array_intersect_key($translated, $english), $english);
        $this->assertSame([], $missing + $extra + $changed, sprintf(
            "lang/%s must match lang/en.\nMissing files/keys: %s\nExtra files/keys: %s\nWrong value types: %s\nAdd or remove the named keys in lang/%s; do not rely on English fallback.",
            $locale,
            implode(', ', array_keys($missing)) ?: '(none)',
            implode(', ', array_keys($extra)) ?: '(none)',
            implode(', ', array_keys($changed)) ?: '(none)',
            $locale,
        ));
    }

    #[Test]
    #[DataProvider('locales')]
    public function placeholders_and_plural_selectors_match_english(string $locale): void
    {
        $translated = Arr::dot($this->catalogue($locale));
        $failures = [];
        foreach (Arr::dot($this->catalogue('en')) as $key => $value) {
            $actual = $translated[$key] ?? null;
            if (!is_string($value) || !is_string($actual)) {
                $failures[] = "$key: expected a translated string, got " . get_debug_type($actual);
                continue;
            }
            foreach ([
                'placeholders' => '/(?<![a-zA-Z0-9_:]):[a-zA-Z_][a-zA-Z0-9_]*/',
                'plural selectors' => '/(?:^|\|)\s*(\{[^}]+\}|\[[^]]+\])/',
            ] as $kind => $pattern) {
                preg_match_all($pattern, $value, $expectedMatches);
                preg_match_all($pattern, $actual, $actualMatches);
                $expectedTokens = $expectedMatches[1] ?? $expectedMatches[0];
                $actualTokens = $actualMatches[1] ?? $actualMatches[0];
                if ($kind === 'placeholders') {
                    sort($expectedTokens);
                    sort($actualTokens);
                }
                if ($expectedTokens !== $actualTokens) {
                    $failures[] = sprintf('%s (%s): expected [%s], got [%s]', $key, $kind, implode(', ', $expectedTokens), implode(', ', $actualTokens));
                }
                if ($kind === 'plural selectors' && $expectedTokens !== [] && substr_count($value, '|') !== substr_count($actual, '|')) {
                    $failures[] = "$key: plural branch count differs from English";
                }
            }
        }
        $this->assertSame([], $failures, "Translation token mismatches in lang/$locale:\n" . implode("\n", $failures));
    }
}
