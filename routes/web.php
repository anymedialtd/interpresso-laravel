<?php

use Illuminate\Support\Facades\Route;
use AnyMedia\Interpresso\Controllers\BatchController;
use AnyMedia\Interpresso\Controllers\LanguageController;
use AnyMedia\Interpresso\Controllers\LoginController;
use AnyMedia\Interpresso\Controllers\LocaleController;
use AnyMedia\Interpresso\Controllers\ManualController;
use AnyMedia\Interpresso\Controllers\NotificationController;
use AnyMedia\Interpresso\Controllers\SettingController;
use AnyMedia\Interpresso\Controllers\TranslationController;
use AnyMedia\Interpresso\Controllers\TranslatorController;

Route::prefix(config('interpresso.prefix'))->middleware(['interpresso.security-headers', config('interpresso.translator_guard')])->group(function (): void {
    Route::get(config('interpresso.login_url'), [LoginController::class, 'index'])->name('interpresso.login');
    Route::post(config('interpresso.login_url'), [LoginController::class, 'login'])->name('interpresso.login.submit');
    Route::post('logout', [LoginController::class, 'logout'])->name('interpresso.logout');
    Route::post('locale', [LocaleController::class, 'update'])->name('interpresso.locale.update');

    Route::middleware([config('interpresso.auth_guard'), 'interpresso.translator'])->group(function (): void {
        Route::get(config('interpresso.languages_url'), [LanguageController::class, 'index'])->name('interpresso.languages');
        Route::get(config('interpresso.translations_url') . '/{language}', [TranslationController::class, 'index'])->name('interpresso.translations');
        Route::get(config('interpresso.manual_url'), [ManualController::class, 'index'])->name('interpresso.manual');
        Route::get('batch/progress', [BatchController::class, 'progress'])->name('interpresso.batch.progress');
        Route::get('notifications', [NotificationController::class, 'index'])->name('interpresso.notifications');
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('interpresso.notifications.read-all');
        Route::post('notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('interpresso.notifications.read');

        Route::prefix(config('interpresso.translations_url') . '/{language}')->group(function (): void {
            Route::get('{id}/modal', [TranslationController::class, 'showTranslateModal'])->whereNumber('id')->name('interpresso.translations.modal');
            Route::post('{id}/suggest', [TranslationController::class, 'openAITranslate'])->whereNumber('id')->name('interpresso.translations.suggest');
            Route::post('{id}/update', [TranslationController::class, 'updateTranslation'])->whereNumber('id')->name('interpresso.translations.update');
            Route::middleware('interpresso.admin')->group(function (): void {
                Route::post('approve', [TranslationController::class, 'approveAllTranslations'])->name('interpresso.translations.approve-all');
                Route::post('export', [TranslationController::class, 'exportTranslationsForLanguage'])->name('interpresso.translations.export');
                foreach (['update-all' => 'updateAllTranslations', 'approve' => 'approveTranslation', 'request' => 'requestTranslation', 'restore-request' => 'restoreRequestTranslation', 'restore' => 'restoreTranslation'] as $path => $method) {
                    Route::post('{id}/' . $path, [TranslationController::class, $method])->whereNumber('id')->name('interpresso.translations.' . $path);
                }
            });
        });

        Route::middleware('interpresso.admin')->group(function (): void {
            Route::prefix(config('interpresso.languages_url'))->group(function (): void {
                Route::post('/', [LanguageController::class, 'store'])->name('interpresso.languages.store');
                Route::post('{language}/delete', [LanguageController::class, 'delete'])->name('interpresso.languages.delete');
                foreach (['import-languages' => 'importLanguages', 'import-translations' => 'importTranslations', 'find-missing' => 'findMissingTranslations', 'approve' => 'approveAllLanguagesTranslations', 'export' => 'exportTranslationsForAllLanguages', 'cancel-jobs' => 'deleteJobs'] as $path => $method) {
                    Route::post($path, [LanguageController::class, $method])->name('interpresso.languages.' . $path);
                }
            });
            Route::prefix(config('interpresso.translators_url'))->group(function (): void {
                Route::get('/', [TranslatorController::class, 'index'])->name('interpresso.translators');
                Route::get('{translator}/edit', [TranslatorController::class, 'index'])->name('interpresso.translators.edit');
                Route::post('/', [TranslatorController::class, 'store'])->name('interpresso.translators.store');
                Route::post('{translator}/update', [TranslatorController::class, 'update'])->name('interpresso.translators.update');
                Route::post('{translator}/delete', [TranslatorController::class, 'delete'])->name('interpresso.translators.delete');
                Route::post('{translator}/password', [TranslatorController::class, 'updateNewPassword'])->name('interpresso.translators.password');
                Route::post('{translator}/notify', [TranslatorController::class, 'notifyPendingTranslations'])->name('interpresso.translators.notify');
            });
            Route::get(config('interpresso.settings_url'), [SettingController::class, 'index'])->name('interpresso.settings');
            Route::post(config('interpresso.settings_url') . '/{field}', [SettingController::class, 'update'])->name('interpresso.settings.update');
        });
    });
});
