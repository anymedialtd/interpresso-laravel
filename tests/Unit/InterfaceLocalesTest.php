<?php

namespace AnyMedia\Interpresso\Tests\Unit;

use AnyMedia\Interpresso\Services\InterfaceLocales;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class InterfaceLocalesTest extends TestCase
{
    #[Test]
    public function available_locales_match_the_package_directories_and_include_every_shipped_language(): void
    {
        $path = dirname(__DIR__, 2) . '/lang';
        $expected = array_map('basename', glob($path . '/*', GLOB_ONLYDIR));
        sort($expected);
        $locales = new InterfaceLocales(new Filesystem());
        $this->assertSame($expected, $locales->codes());
        foreach (['en', 'de', 'fr', 'es', 'it'] as $locale) {
            $this->assertTrue($locales->supports($locale));
        }
    }

    #[Test]
    public function a_new_translation_is_discovered_and_discovery_is_cached_per_application_lifetime(): void
    {
        $files = new Filesystem();
        $directory = sys_get_temp_dir() . '/interpresso-locales-' . bin2hex(random_bytes(8));
        try {
            $files->ensureDirectoryExists($directory . '/nl');
            $files->put($directory . '/nl/global.php', "<?php return ['locale_name' => 'Nederlands'];");
            $files->ensureDirectoryExists($directory . '/empty');
            $files->ensureDirectoryExists($directory . '/invalid.locale');
            $files->put($directory . '/invalid.locale/global.php', '<?php return [];');
            $files->put($directory . '/fr.json', '{}');
            $locales = new InterfaceLocales($files, $directory);
            $this->assertSame(['nl'], $locales->codes());

            $files->ensureDirectoryExists($directory . '/pt_BR');
            $files->put($directory . '/pt_BR/global.php', '<?php return [];');
            $this->assertSame(['nl'], $locales->codes());
            $this->assertSame(['nl', 'pt_BR'], (new InterfaceLocales($files, $directory))->codes());
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
