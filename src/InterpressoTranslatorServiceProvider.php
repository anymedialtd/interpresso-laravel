<?php

namespace AnyMedia\Interpresso;

use Illuminate\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Translation\TranslationServiceProvider;
use AnyMedia\Interpresso\Helpers\LanguageHelper;

class InterpressoTranslatorServiceProvider extends TranslationServiceProvider
{
    /**
     * Bootstrap the package services.
     *
     * @return void
     */
    public function boot(): void
    {

    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        if(config('interpresso.enabled', true)) {
            $this->app->singleton(LanguageHelper::class, function () {
                return new LanguageHelper();
            });
        }

        parent::register();
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader(): void
    {
        if(!config('interpresso.enabled', true)) {
            parent::registerLoader();
            return;
        }
        if(app()->runningInConsole()) {
            try {
                /** @var string|\UnitEnum|null $connection Configured database connection name. */
                $connection = config('interpresso.db_connection');
                DB::connection($connection)->getDatabaseName();
                $this->loadTranslationsArray();
            } catch (\Exception) {
                parent::registerLoader();
            }
        } else {
            $this->loadTranslationsArray();
        }
    }

    /**
     * @return void
     */
    protected function loadTranslationsArray(): void
    {
        /** @var string $cacheKey Configured cache key prefix. */
        $cacheKey = config('interpresso.cache_key');
        $hasDBLoaderOn = Cache::rememberForever($cacheKey . '_has_db_loader_on', function () {
            /** @var string|null $connection Connection name accepted by the schema builder. */
            $connection = config('interpresso.db_connection');
            /** @var string $table Configured settings table name. */
            $table = config('interpresso.table_settings');
            if (!Schema::connection($connection)->hasTable($table)) {
                return false;
            }
            $setting = DB::connection($connection)->table($table)->first();
            return (bool) ($setting->db_loader ?? false);
        });
        if($hasDBLoaderOn) {
            $this->app->singleton('translation.loader', function (Application $app): TranslationLoader {
                /** @var Filesystem $files Laravel's filesystem service binding. */
                $files = $app['files'];
                /** @var string $path Laravel's language path binding. */
                $path = $app['path.lang'];
                /** @var string $providerFile The installed PHP translation provider, including standalone illuminate installs. */
                $providerFile = (new \ReflectionClass(TranslationServiceProvider::class))->getFileName();
                // Match Laravel's loader paths so built-in validation messages
                // remain available even before the host publishes lang files.
                return new TranslationLoader($files, [dirname($providerFile) . '/lang', $path]);
            });
        } else {
            parent::registerLoader();
        }
    }
}
