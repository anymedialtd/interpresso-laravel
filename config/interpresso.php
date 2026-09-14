<?php

/**
 * Stores the config of the Interpresso package
 */
return [
    /*
      |--------------------------------------------------------------------------
      | Disable
      |--------------------------------------------------------------------------
      |
      | If handling translations on multiple server add here your primary server.
      | This will also be the primary entry for developer DB content download
      */
    'enabled' => env('INTERPRESSO_ENABLED', true),

    // Default interface language. Translator and browser preferences take priority.
    // Null follows app.locale when it is available in the package's lang directory.
    'locale' => env('INTERPRESSO_LOCALE'),

    // Headers apply only to package web routes, including the login page.
    'security_headers' => [
        'enabled' => env('INTERPRESSO_SECURITY_HEADERS_ENABLED', true),
        // Append explicit HTTP(S) origins to script-src, style-src, img-src,
        // font-src or connect-src. The strict base policy cannot be replaced.
        // Example: 'script-src' => ['https://cdn.example.com'],
        //          'style-src' => ['https://cdn.example.com'],
        'extra_sources' => [],
    ],

    /*
   |--------------------------------------------------------------------------
   | MAIN SERVER URL
   |--------------------------------------------------------------------------
   |
   | If handling translations on multiple server add here your primary server.
   | This will also be the primary entry for developer DB content download
   */
    'main_server_domain' => env('INTERPRESSO_MAIN_SERVER_DOMAIN', config('app.url')),

    /*
    |--------------------------------------------------------------------------
    | Handles Decentralised DB
    |--------------------------------------------------------------------------
    |
    | INTERPRESSO_DB_CONNECTION: DB Connection set a custom connection in your config/database.php
    | INTERPRESSO_API_SHARED_SECRET: Add the same shared key env on each server
    | INTERPRESSO_MULTIPLE_DB_HOSTS: Add a comma separated list of domains where running the app
    */

    'db_connection' => env('INTERPRESSO_DB_CONNECTION', config('database.default')),

    'api_shared_api_key' => env('INTERPRESSO_API_SHARED_SECRET'),

    'multiple_db_hosts' => env('INTERPRESSO_MULTIPLE_DB_HOSTS', ''),

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    |
    | The Interpresso route paths.
    |
    | Change if you would like to use different the URL paths.
    |
    | Default settings: ./translator/login, ./translator/languages...
    |
    */
    'prefix' => 'translator',

    'languages_url' => 'languages',

    'translations_url' => 'translations',

    'translators_url' => 'translators',

    'settings_url' => 'settings',

    'manual_url' => 'manual',

    'login_url' => 'login',

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | The Interpresso table names.
    |
    | Change these lines only before running the migrations. Else it will be necessary to manually change the table names afterwards.
    |
    */
    'table_languages' => env('INTERPRESSO_TABLE_LANGUAGES', 'interpresso_languages'),

    'table_translations' => env('INTERPRESSO_TABLE_TRANSLATIONS', 'interpresso_translations'),

    'table_translators' => env('INTERPRESSO_TABLE_TRANSLATORS', 'interpresso_translators'),

    'table_password_reset_tokens' => env('INTERPRESSO_TABLE_PASSWORD_RESET_TOKENS', 'interpresso_password_reset_tokens'),

    'password_reset' => [
        // Minutes. Invitations and password resets share the same single-use tokens.
        'expire' => env('INTERPRESSO_PASSWORD_RESET_EXPIRE', 60),
        'throttle' => 60, // Seconds between emails for the same translator.
        // Public requests always enqueue, even for unknown addresses. A real
        // worker keeps account lookup and SMTP timing out of the HTTP response.
        // Uses queue_name below. Sync/deferred/null connections are rejected.
        'queue_connection' => env('INTERPRESSO_PASSWORD_RESET_QUEUE_CONNECTION', 'database'),
    ],

    'table_settings' => env('INTERPRESSO_TABLE_SETTINGS', 'interpresso_settings'),

    'table_translator_language' => env('INTERPRESSO_TABLE_TRANSLATOR_LANGUAGE', 'interpresso_language'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Set the queue name and batch name for the Interpresso background jobs
    |
    */
    'queue_name' => 'languageProcessor',

    'batch_name' => 'languageBatch',

    // Maximum source rows per cursor job. Lower this on hosts with short process caps.
    'chunk_size' => env('INTERPRESSO_CHUNK_SIZE', 100),

    // Bounded queue:work invocation for cron. All limits must be positive integers.
    // max_time (seconds) and memory (MB) are checked between jobs; timeout is
    // Laravel's per-job limit in seconds (requires PCNTL in PHP CLI).
    'queue_worker' => [
        'max_time' => env('INTERPRESSO_QUEUE_WORKER_MAX_TIME', 50),
        'max_jobs' => env('INTERPRESSO_QUEUE_WORKER_MAX_JOBS', 100),
        'memory' => env('INTERPRESSO_QUEUE_WORKER_MEMORY', 96),
        'timeout' => env('INTERPRESSO_QUEUE_WORKER_TIMEOUT', 60),
    ],

    // Opt in independently. No entries are registered for a non-deferring queue.
    // Shared hosting: enable queue_worker with QUEUE_CONNECTION=database and
    // invoke php artisan schedule:run from one cron entry every minute.
    'schedule' => [
        'queue_worker' => env('INTERPRESSO_SCHEDULE_QUEUE_WORKER', false),
        // Uses an in-process foreground callback if disabled or proc_open is unavailable.
        'worker_background' => env('INTERPRESSO_SCHEDULE_WORKER_BACKGROUND', true),
        'prune_batches' => env('INTERPRESSO_SCHEDULE_PRUNE_BATCHES', false),
        'pending_notifications' => env('INTERPRESSO_SCHEDULE_PENDING_NOTIFICATIONS', false),
    ],

    // Seconds before an abandoned process lock expires. Long imports should
    // heartbeat through the acquired ProcessLock handle's refresh() method.
    'process_lock_ttl' => env('INTERPRESSO_PROCESS_LOCK_TTL', 1800),

    'prune_batch_hours' => 24, // Prunes all finished or cancelled batches older than this value (value in hours)

    /*
    |--------------------------------------------------------------------------
    | Guard Translator
    |--------------------------------------------------------------------------
    |
    | Set the guard name for the translator UI, will be assigned to model translator
    |
    */
    'translator_guard' => 'interpresso_translator',

    'auth_guard' => 'auth_translator',


    /*
    |--------------------------------------------------------------------------
    | General Settings
    |--------------------------------------------------------------------------
    |
    */

    'cache_key' => 'interpresso_cache',

    /*
    |--------------------------------------------------------------------------
    | Translatable models
    |--------------------------------------------------------------------------
    |
    | Set the translatable models in the array each model must have the property
    |
    | public array $translatable:
    |
    | e.g. public array $translatable = ['label']; or
    | public array $translatable = ['label', 'name']; for multiple implementations
    |
    | each DB table column (e.g. above label or name) must support json (LONGTEXT)
    |
    | each column will have the language codes as key and translates values as value
    | e.g.: {"en":"English","it":"inglese","de":"English"}
    |
    | We suggest using it with the package: spatie/laravel-translatable
    */

    'translatable_models' => [
        //        \App\Models\User::class
    ],

    /*
    |--------------------------------------------------------------------------
    | OPEN AI models
    |--------------------------------------------------------------------------
    |
    | Here you can change the open api model: gpt-3.5-turbo should do a good job for a good price
    */
    'open_ai_model' => 'gpt-3.5-turbo-1106',

    'max_open_ai_missing_trans' => 50, // the translator translates multiple array values if you have longer text reduce this number

];
