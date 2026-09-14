<?php

namespace AnyMedia\Interpresso\Tests\e2e;

use Illuminate\Support\ServiceProvider;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Services\OpenAITranslationService;

// Only registered by standalone Testbench; inert during the PHPUnit suite.
class TestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (getenv('INTERPRESSO_E2E') !== '1') {
            return;
        }
        $directory = __DIR__ . '/.data';
        $this->app->useLangPath($directory . '/lang');
        config([
            'app.locale' => is_file($directory . '/locale') ? trim(file_get_contents($directory . '/locale')) : 'en',
            'cache.default' => 'file',
            'cache.stores.file.path' => $directory . '/cache',
            'session.files' => $directory . '/sessions',
            'mail.default' => 'array',
            'queue.default' => is_file($directory . '/queue-connection') ? 'database' : 'sync',
            'queue.connections.database.connection' => 'sqlite',
            'queue.batching.database' => 'sqlite',
            'queue.failed.database' => 'sqlite',
        ]);
        // The only external-service double. Controllers, jobs and persistence
        // still execute normally, and no paid API or real email is contacted.
        $this->app->bind(OpenAITranslationService::class, fn () => new class extends OpenAITranslationService {
            public function translateString(?Language $rootLanguage, ?Language $toLanguage, ?string $text): ?string
            {
                return $text === null ? null : '[' . $toLanguage?->code . '] ' . $text;
            }
        });
    }
}
