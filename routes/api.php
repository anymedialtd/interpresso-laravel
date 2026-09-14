<?php

use Illuminate\Support\Facades\Route;
use AnyMedia\Interpresso\Controllers\Api\SettingsController;
use AnyMedia\Interpresso\Controllers\Api\LanguagesController;
use AnyMedia\Interpresso\Controllers\Api\TranslationsController;

/**
 * App Routes
 */
Route::middleware(['interpresso.locale', 'interpresso-auth-api'])->prefix('api')->group(function() {
    Route::post('cancelJobs', [LanguagesController::class, 'cancelBatch'])->name('interpresso.api.cancel-batch');
    Route::post('interpresso-has-jobs-running', [SettingsController::class, 'jobsOnOtherDBRunning'])->name('interpresso.api.jobs-running');
    Route::post('interpresso-get-languages', [LanguagesController::class, 'getLanguages'])->name('interpresso.api.get-languages');
    Route::post('interpresso-get-paginated-translations', [TranslationsController::class, 'getPaginated'])->name('interpresso.api.get-paginated-translations');
    Route::post('interpresso-force-export', [TranslationsController::class, 'forceExport'])->name('interpresso.api.force-export');
});
Route::middleware('interpresso.locale')->prefix('api')->get('version', function() {
    return response()->json(
        ['version' => \AnyMedia\Interpresso\InterpressoServiceProvider::$version]
    );
})->name('interpresso.api.version');
// To DO future API development
//Route::prefix(config('interpresso.api.root_prefix'))->group(function () {
//    Route::get('/' . config('interpresso.languages_url'), Languages::class)->name('interpresso.languages');
//    Route::get('/' . config('interpresso.translations_url') . '/{language}', Translations::class)->name('interpresso.translations');
//});

