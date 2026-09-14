<?php

namespace AnyMedia\Interpresso;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\ServiceProvider;
use AnyMedia\Interpresso\Console\Commands\ApproveTranslations;
use AnyMedia\Interpresso\Console\Commands\DeveloperDownloadToLocalCommand;
use AnyMedia\Interpresso\Console\Commands\ExportTranslationAfterDeployment;
use AnyMedia\Interpresso\Console\Commands\ExportTranslations;
use AnyMedia\Interpresso\Console\Commands\FindMissingTranslations;
use AnyMedia\Interpresso\Console\Commands\ImportLanguages;
use AnyMedia\Interpresso\Console\Commands\ImportTranslations;
use AnyMedia\Interpresso\Console\Commands\PruneLanguageBatches;
use AnyMedia\Interpresso\Console\Commands\SendAutomaticPendingNotifications;
use AnyMedia\Interpresso\Console\Commands\Unlock;
use AnyMedia\Interpresso\Console\Commands\Work;
use AnyMedia\Interpresso\Middleware\AuthApi;
use AnyMedia\Interpresso\Middleware\AuthTranslator;
use AnyMedia\Interpresso\Middleware\EncryptCookies;
use AnyMedia\Interpresso\Middleware\SecurityHeaders;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\OpenAITranslationService;
use AnyMedia\Interpresso\Services\QueueConfiguration;
use AnyMedia\Interpresso\Services\ProcessCapabilities;


class InterpressoServiceProvider extends ServiceProvider
{
    public static string $version = '2.0.0';

    protected null|bool|object $settings = false;
    /**
     * Bootstrap the package services.
     *
     * @return void
     */
    public function boot(): void
    {
        if(!config('interpresso.enabled', true)) {
            return;
        }
//        if(Schema::connection(config('interpresso.db_connection'))->hasTable(config('interpresso.table_settings'))) {
//            $this->settings = DB::connection(config('interpresso.db_connection'))->table(config('interpresso.table_settings'))->first();
//        }
        $this->publishes([
            __DIR__ . '/../config/interpresso.php' => config_path('interpresso.php'),
            __DIR__ . '/../config/openai.php' => config_path('openai.php') // Creates an open ai config
        ], 'interpresso-config',
        );
        $this->addMiddleware();
        $this->setCustomGuard();
        $this->loadTranslations();
        $this->loadRoutes();
        $this->loadViews();
        view()->composer('interpresso::partials.batch-progress', function (\Illuminate\View\View $view): void {
            $view->with('batch', resolve(\AnyMedia\Interpresso\Services\BatchService::class)->progress());
        });
        // Translation values are content: leading and trailing whitespace can be
        // meaningful, so exempt the field from global input trimming.
        TrimStrings::except(['translatedValue']);
        $this->loadMigrations();
        $this->loadAssets();
        $this->loadCommands();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            if (!QueueConfiguration::defersWork()) {
                return;
            }

            $canSpawn = resolve(ProcessCapabilities::class)->canSpawn();
            $command = static function (string $name, bool $foreground = false) use ($schedule, $canSpawn): Event {
                if ($canSpawn && !$foreground) return $schedule->command($name);
                // Even a foreground command event uses Symfony Process/proc_open.
                // A named callback executes Artisan inside the scheduler process.
                return $schedule->call(static fn (): int => Artisan::call($name))->name($name);
            };

            // Run maintenance before starting a fresh worker so it can release
            // its operation lease before newly queued jobs start executing.
            if (config('interpresso.schedule.prune_batches', false)) {
                $command('interpresso:prune-batches')->everyMinute()->withoutOverlapping();
            }
            if (config('interpresso.schedule.pending_notifications', false)) {
                $command('interpresso:send-automatic-pending-translations-notification')->daily()->withoutOverlapping();
            }
            if (config('interpresso.schedule.queue_worker', false)) {
                // Laravel's cache mutex prevents overlapping scheduled workers.
                // ProcessLock is a separate DB lease guarding operations across
                // HTTP, CLI and batch jobs; neither lock replaces the other.
                $background = $canSpawn && config('interpresso.schedule.worker_background', true);
                $worker = $command('interpresso:work', !$background)->everyMinute()
                    ->withoutOverlapping(QueueConfiguration::workerOverlapMinutes());
                if ($background) $worker->runInBackground();
            }
        });
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/interpresso.php', 'interpresso');

        if(!config('interpresso.enabled', true)) {
            return;
        }
        $this->app->singleton(OpenAITranslationService::class, function () {
            return new OpenAITranslationService();
        });
    }

    /**
     * Creates the new translator guard
     *
     * @return void
     */
    protected function setCustomGuard(): void
    {
        /** @var string $translatorGuard Configured translator guard name. */
        $translatorGuard = config('interpresso.translator_guard');
        Config::set('auth.guards.' . $translatorGuard, [
            'driver' => 'session',
            'provider' => 'translators',
        ]);

        Config::set('auth.providers.translators', [
            'driver' => 'eloquent',
            'model' => Translator::class,
        ]);

        Config::set('auth.passwords.translators', [
            'provider' => 'translators',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ]);
    }

    /**
     * Adds the required middleware for the translator guard
     *
     * @return void
     */
    protected function addMiddleware(): void
    {
        /** @var string $translatorGuard Configured translator guard name. */
        $translatorGuard = config('interpresso.translator_guard');
        /** @var string $authGuard Configured authentication middleware alias. */
        $authGuard = config('interpresso.auth_guard');
        app('router')->aliasMiddleware($authGuard, AuthTranslator::class);
        app('router')->aliasMiddleware('interpresso.translator', \AnyMedia\Interpresso\Middleware\EnsureTranslator::class);
        app('router')->aliasMiddleware('interpresso.admin', \AnyMedia\Interpresso\Middleware\EnsureAdmin::class);
        app('router')->aliasMiddleware('interpresso.security-headers', SecurityHeaders::class);
        app('router')->aliasMiddleware('interpresso-auth-api', AuthApi::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, EncryptCookies::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, \Illuminate\Session\Middleware\StartSession::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, \Illuminate\View\Middleware\ShareErrorsFromSession::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
        app('router')->pushMiddlewareToGroup($translatorGuard, \Illuminate\Routing\Middleware\SubstituteBindings::class);
        app('router')->aliasMiddleware($translatorGuard, \AnyMedia\Interpresso\Middleware\Translator::class);
    }

    /**
     * @return void
     */
    protected function loadTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'interpresso');

        $this->publishes([
            __DIR__ . '/../lang' => $this->app->langPath('vendor/interpresso')], 'interpresso-translations',
        );
    }

    /**
     * @return void
     */
    protected function loadRoutes(): void
    {
        if(config('interpresso.main_server_domain') === config('app.url')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    /**
     * @return void
     */
    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'interpresso');
        view()->composer('interpresso::layouts.app', function (\Illuminate\View\View $view): void {
            $theme = request()->cookie('interpresso-color-theme');
            /** @var string $prefix Configured package URL prefix. */
            $prefix = config('interpresso.prefix');
            $view->with('colorTheme', in_array($theme, ['light', 'dark'], true) ? $theme : null);
            $view->with('themeCookiePath', parse_url(url($prefix), PHP_URL_PATH) ?: '/');
        });
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/interpresso')], 'interpresso-views',
        );
    }

    /**
     * @return void
     */
    protected function loadMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations')
        ], 'interpresso-migrations');
    }

    /**
     * @return void
     */
    protected function loadAssets(): void
    {
        $this->publishes([
            __DIR__ . '/../public' => public_path('vendor/interpresso'),
        ], 'interpresso-public');
    }

    /**
     * @return void
     */
    protected function loadCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneLanguageBatches::class,
                Work::class,
                Unlock::class,
                ImportLanguages::class,
                ImportTranslations::class,
                FindMissingTranslations::class,
                ApproveTranslations::class,
                ExportTranslations::class,
                SendAutomaticPendingNotifications::class,
                DeveloperDownloadToLocalCommand::class,
                ExportTranslationAfterDeployment::class
            ]);
        } else {
            $this->commands([
                ExportTranslations::class,
            ]);
        }
    }
}
