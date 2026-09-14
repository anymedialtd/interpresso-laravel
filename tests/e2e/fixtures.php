<?php

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Notifications\FlashMessage;
use AnyMedia\Interpresso\Tests\e2e\Article;

// Expose the configured table names for direct browser-test database assertions.
// .data is gitignored, so it does not exist on a fresh checkout or in CI - and the
// PHPUnit suite requires this file too, via InteractsWithBackgroundProcesses.
File::ensureDirectoryExists(__DIR__ . '/.data');
File::put(__DIR__ . '/.data/tables.json', json_encode([
    'translations' => (new Translation())->getTable(),
    'settings' => (new Setting())->getTable(),
], JSON_THROW_ON_ERROR));

$english = Language::where('code', 'en')->firstOrFail();
Language::create(['code' => 'de', 'name' => 'German', 'native_name' => 'Deutsch']);
$translator = Translator::create([
    'email' => 'translator@example.test',
    'password' => Hash::make('translator-password'),
    'first_name' => 'Regular',
    'last_name' => 'Translator',
    'phone' => '+41000000001',
    'admin' => false,
]);
$translator->languages()->sync([$english->id]);
Setting::firstOrFail()->update(['allow_deleting_languages' => true]);

// key, value, needs translation, approved, updated, vendor, exported.
// Every filter has a different matching set, so filtering on the wrong field
// cannot accidentally satisfy the browser assertions.
$rows = [
    ['welcome', 'Welcome home', false, true, false, false, true],
    ['checkout', 'Finish your order', true, false, false, false, false],
    ['profile', 'Your profile', false, false, true, false, false],
    ['vendor_notice', 'Vendor notice', false, true, false, true, false],
    ['vendor_pending', 'Vendor draft', true, false, true, true, false],
    ['vendor_ready', 'Vendor ready', false, true, false, true, true],
];
foreach ($rows as [$key, $value, $needs, $approved, $updated, $vendor, $exported]) {
    Translation::create([
        'language_id' => $english->id,
        'language_code' => $english->code,
        'shared_identifier' => hash('sha256', 'e2e.'.$key),
        'type' => 'php',
        'namespace' => $vendor ? 'e2e-vendor' : '',
        'group' => 'e2e',
        'key' => $key,
        'value' => $value,
        'old_value' => $updated ? 'Previous '.$value : null,
        'needs_translation' => $needs,
        'approved' => $approved,
        'updated_translation' => $updated,
        'is_vendor' => $vendor,
        'exported' => $exported,
    ]);
}
if ($translator->fresh()->admin || $translator->languages()->count() !== 1
    || !Hash::check('translator-password', $translator->fresh()->password)
    || $english->translations()->count() !== count($rows)) {
    throw new RuntimeException('E2E fixtures were not persisted correctly.');
}

$scenario = $scenario ?? (getenv('E2E_SCENARIO') ?: 'base');
$english = Language::where('code', 'en')->firstOrFail();
$german = Language::where('code', 'de')->firstOrFail();
$admin = Translator::where('email', 'admin@admin.com')->firstOrFail();
$translator = Translator::where('email', 'translator@example.test')->firstOrFail();

$copy = function (Translation $source, array $attributes): Translation {
    $row = $source->replicate();
    $row->fill($attributes)->save();
    return $row;
};

if ($scenario === 'filters') {
    foreach (['welcome' => ['php', $admin->id, $admin->id], 'checkout' => ['json', $translator->id, null],
        'profile' => ['model', $admin->id, $translator->id], 'vendor_notice' => ['json', $translator->id, $admin->id],
        'vendor_pending' => ['model', null, $translator->id], 'vendor_ready' => ['php', null, null]] as $key => [$type, $updater, $approver]) {
        Translation::where('key', $key)->firstOrFail()->update(['type' => $type, 'updated_by' => $updater, 'approved_by' => $approver]);
    }
}

if (in_array($scenario, ['examples', 'bulk', 'queued'], true)) {
    $copy(Translation::where('key', 'welcome')->firstOrFail(), [
        'language_id' => $german->id, 'language_code' => 'de', 'value' => 'Willkommen zu Hause',
        'approved' => false, 'exported' => false,
    ]);
}

if ($scenario === 'imports') {
    File::ensureDirectoryExists(app()->langPath('it'));
    File::put(app()->langPath('en/browser.php'), "<?php return ['imported' => 'Imported from a real PHP file'];");
    File::put(app()->langPath('en.json'), json_encode(['Imported JSON key' => 'Imported from a real JSON file'], JSON_THROW_ON_ERROR));
}

if ($scenario === 'notifications') {
    $admin->notifyNow(new FlashMessage('First browser notification'));
    $admin->notifyNow(new FlashMessage('Second browser notification'));
}

if ($scenario === 'running') {
    $lock = resolve(\AnyMedia\Interpresso\Services\ProcessLock::class);
    $lock->acquire('e2e-host:123 fixture batch', 900);
    $batch = Bus::batch([])->withOption('process_lock', $lock)->name(config('interpresso.batch_name'))->dispatch();
    DB::table('job_batches')->where('id', $batch->id)->update(['total_jobs' => 2, 'pending_jobs' => 1, 'finished_at' => null]);
    DB::table('jobs')->insert(['queue' => config('interpresso.queue_name'), 'payload' => '{}', 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
}

if (in_array($scenario, ['locked', 'expired-lock'], true)) {
    $lock = resolve(\AnyMedia\Interpresso\Services\ProcessLock::class);
    $lock->acquire('cron-host:123 import translations', 900);
    if ($scenario === 'expired-lock') {
        Setting::query()->update(['process_started_at' => now()->subHour(), 'process_expires_at' => now()->subSecond()]);
    }
}

if ($scenario === 'pagination') {
    $source = Translation::where('key', 'welcome')->firstOrFail();
    for ($number = 1; $number <= 25; $number++) {
        $key = sprintf('paging_%02d', $number);
        $copy($source, ['key' => $key, 'shared_identifier' => hash('sha256', $key), 'value' => 'Page value ' . $number,
            'updated_by' => $admin->id, 'approved_by' => $admin->id, 'is_vendor' => true]);
    }
    for ($number = 1; $number <= 13; $number++) {
        $person = $translator->replicate();
        $person->fill(['email' => sprintf('paging-%02d@example.test', $number), 'first_name' => 'Paging', 'phone' => null])->save();
        $person->languages()->sync([$english->id]);
    }
    foreach (array_slice(Language::LANGUAGES, 0, 25) as $attributes) {
        if (!Language::where('code', $attributes['code'])->exists()) {
            $attributes['name'] = 'Paging ' . $attributes['name'];
            Language::create($attributes);
        }
    }
}

if ($scenario === 'models') {
    Schema::create('e2e_articles', function ($table): void {
        $table->id();
        $table->text('title');
    });
    DB::table('e2e_articles')->insert(['id' => 1, 'title' => json_encode(['en' => 'Old English', 'de' => 'Old German'])]);
    foreach ([$english, $german] as $language) {
        $copy(Translation::where('key', 'welcome')->firstOrFail(), [
            'language_id' => $language->id, 'language_code' => $language->code,
            'type' => 'model', 'namespace' => Article::class, 'group' => 'title', 'key' => '1',
            'shared_identifier' => 'e2e-article-title', 'value' => 'Model ' . $language->name,
            'approved' => true, 'exported' => false,
        ]);
    }
}

if ($scenario === 'queued') {
    File::put(__DIR__ . '/.data/queue-connection', 'database');
}

if (!in_array($scenario, ['base', 'filters', 'examples', 'bulk', 'imports', 'notifications', 'running', 'locked', 'expired-lock', 'pagination', 'models', 'queued'], true)) {
    throw new RuntimeException('Unknown E2E fixture scenario: ' . $scenario);
}
