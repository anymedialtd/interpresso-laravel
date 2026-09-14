<?php

namespace AnyMedia\Interpresso\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use AnyMedia\Interpresso\Jobs\ApproveLanguagesJob;
use AnyMedia\Interpresso\Jobs\Batch\BatchProcessor;
use AnyMedia\Interpresso\Jobs\ExportTranslationJob;
use AnyMedia\Interpresso\Jobs\UpdateTranslationJob;
use AnyMedia\Interpresso\Models\Language;
use AnyMedia\Interpresso\Models\Setting;
use AnyMedia\Interpresso\Models\Translation;
use AnyMedia\Interpresso\Models\Translator;
use AnyMedia\Interpresso\Services\ApproveLanguagesService;
use AnyMedia\Interpresso\Services\ExportTranslationService;
use AnyMedia\Interpresso\Services\OpenAITranslationService;
use AnyMedia\Interpresso\Services\Toast;
use AnyMedia\Interpresso\Services\Traits\ChecksForRunningJobs;

class TranslationController extends BaseController
{
    use ChecksForRunningJobs;

    public const STATE_FILTERS = ['needs_translation', 'approved', 'updated_translation', 'is_vendor', 'exported'];

    public function index(Request $request, Language $language): View
    {
        $this->authorizeLanguage($language);
        $rules = [
            'search' => 'nullable|string',
            'types' => 'sometimes|array', 'types.*' => 'in:php,json,model',
            'updatedBy' => 'sometimes|array', 'updatedBy.*' => 'integer',
            'approvedBy' => 'sometimes|array', 'approvedBy.*' => 'integer',
        ];
        foreach (self::STATE_FILTERS as $field) {
            $rules[$field] = 'nullable|in:true,false,1,0';
        }
        $request->validate($rules);
        $query = $language->translations()->with('approvedBy', 'updatedBy');
        $search = $request->string('search')->toString();
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                foreach (['namespace', 'id', 'group', 'key', 'value', 'old_value'] as $field) {
                    $query->orWhere($field, 'LIKE', '%' . $search . '%');
                }
            });
        }
        $filters = [];
        foreach (self::STATE_FILTERS as $field) {
            $filters[$field] = $request->filled($field) ? $request->boolean($field) : null;
            if ($filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['types' => 'type', 'updatedBy' => 'updated_by', 'approvedBy' => 'approved_by'] as $parameter => $column) {
            /** @var list<string|int> $values Validated array filters. */
            $values = $request->input($parameter, []);
            if ($values !== []) {
                $query->whereIn($column, $values);
            }
        }
        $languages = $this->scopeLanguages(Language::query())->orderBy('code')->get();
        return view('interpresso::translations', [
            'language' => $language,
            'languages' => $languages,
            'exampleLanguageId' => $languages->firstWhere('code', config('app.fallback_locale'))->id ?? $language->id,
            'translators' => Translator::pluck('email', 'id')->all(),
            'data' => $query->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'search' => $search,
        ]);
    }

    /** @return Collection<int, Translation> */
    private function examples(Translation $translation): Collection
    {
        return Translation::query()->where('shared_identifier', $translation->shared_identifier)
            ->whereIn('language_id', $this->scopeLanguages(Language::query())->select('id'))
            ->with('language')->orderBy('language_id')->get();
    }

    /** @param Collection<int, Translation> $examples */
    private function example(Request $request, Collection $examples): ?Translation
    {
        $request->validate(['example_language' => 'nullable|integer']);
        $fallback = $examples->firstWhere('language_code', config('app.fallback_locale'));
        if ($request->filled('example_language')) {
            $selectedLanguage = Language::query()->findOrFail($request->integer('example_language'));
            $this->authorizeLanguage($selectedLanguage);
            $selected = $examples->firstWhere('language_id', $selectedLanguage->id);
        } else {
            $selected = $fallback;
        }
        return $selected !== null && $selected->value !== null && $selected->value !== '' ? $selected : $fallback;
    }

    public function showTranslateModal(Request $request, Language $language, int $id): JsonResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        $examples = $this->examples($translation);
        $example = $this->example($request, $examples);
        $parameters = ['language' => $language, 'id' => $id];
        return response()->json([
            'id' => $translation->id,
            'key' => ($translation->namespace ? $translation->namespace . '::' : '')
                . ($translation->group ? $translation->group . '.' : '') . $translation->key,
            'value' => $translation->value ?? '',
            'example' => $example ? ['value' => $example->value, 'language_id' => $example->language_id, 'code' => $example->language_code] : null,
            'examples' => $examples->map(fn (Translation $row): array => [
                'code' => $row->language_code, 'value' => $row->value ?? '', 'language_id' => $row->language_id,
            ])->all(),
            'save_url' => route('interpresso.translations.update', $parameters),
            'suggest_url' => route('interpresso.translations.suggest', $parameters),
            'update_all_url' => route('interpresso.translations.update-all', $parameters),
            'can_suggest' => Setting::getCached()->enable_open_ai_translations && $example !== null && $example->value !== null && $example->value !== '',
            'can_update_all' => $this->isAdministrator() && Setting::getCached()->enable_open_ai_translations && $language->code === config('app.locale'),
        ]);
    }

    public function openAITranslate(Request $request, Language $language, int $id, OpenAITranslationService $service): JsonResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        abort_unless(Setting::getCached()->enable_open_ai_translations, 403);
        $example = $this->example($request, $this->examples($translation));
        if ($example === null || $example->value === null || $example->value === '') {
            return response()->json(['message' => __('interpresso::translations.no_translation_example')], 422);
        }
        try {
            $value = $service->translateString($example->language, $language, $example->value);
        } catch (\Exception $exception) {
            Log::warning('Translation suggestion failed.', ['exception' => $exception::class]);
            return response()->json(['message' => __('interpresso::global.something_wrong')], 502);
        }
        return response()->json(['value' => $value]);
    }

    private function draft(Request $request): string
    {
        $request->validate(['translatedValue' => 'present|nullable|string']);
        return $request->string('translatedValue')->toString();
    }

    private function saveDraft(Translation $translation, string $value): void
    {
        if ($translation->value === $value) {
            return;
        }
        if (!$translation->updated_translation) {
            $translation->previous_approved_by = $translation->approved_by;
            $translation->previous_updated_by = $translation->updated_by;
            $translation->old_value = $translation->value;
        }
        $translation->fill([
            'exported' => false, 'needs_translation' => false, 'approved_by' => null,
            'updated_by' => $this->authUser()->id, 'updated_translation' => true,
            'value' => $value, 'approved' => false,
        ])->save();
    }

    private function backToLanguage(Language $language): RedirectResponse
    {
        // Filters are submitted in the action URL, never as an arbitrary redirect URL.
        return redirect()->route('interpresso.translations', ['language' => $language] + $this->request->query());
    }

    public function updateTranslation(Request $request, Language $language, int $id): RedirectResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        $value = $this->draft($request);
        if (($lock = $this->acquireProcessLock('update translation')) !== null) {
            try {
                $this->saveDraft($translation, $value);
                Toast::flash(__('interpresso::translations.update_success_message'), 'SUCCESS', 4000);
            } finally {
                $lock->release();
            }
        }
        return $this->backToLanguage($language);
    }

    public function updateAllTranslations(Request $request, Language $language, int $id, BatchProcessor $processor): RedirectResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        abort_unless(Setting::getCached()->enable_open_ai_translations && $language->code === config('app.locale'), 403);
        $value = $this->draft($request);
        if (!$this->canDispatchBatch()) {
            return $this->backToLanguage($language);
        }
        if (($lock = $this->acquireProcessLock('update translations for all languages')) === null) {
            return $this->backToLanguage($language);
        }
        try {
            if ($translation->value !== $value) {
                $jobs = [];
                foreach ($this->examples($translation) as $example) {
                    if ($example->id !== $translation->id) {
                        $jobs[] = new UpdateTranslationJob($language, $example, $value, $this->authUser());
                    }
                }
                $this->saveDraft($translation, $value);
                if ($jobs !== []) {
                    $batch = $processor->dispatch($jobs, lock: $lock, then: function () use ($translation): void {
                        Translator::notifyAdminUpdatedAllLanguages($translation);
                    });
                    session()->flash('batch_id', $batch->id);
                }
                Toast::flash(__('interpresso::translations.update_success_message'), 'SUCCESS', 4000);
            }
            return $this->backToLanguage($language);
        } finally {
            $lock->release();
        }
    }

    public function approveTranslation(Language $language, int $id, ApproveLanguagesService $service): RedirectResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        if (($lock = $this->acquireProcessLock('approve translation')) !== null) {
            try {
                $translation->update($service->approvedTranslationUpdateArray($this->authUser()->id));
                $service->resetTranslationCache($translation);
                Toast::flash(__('interpresso::translations.approved_success'));
            } finally {
                $lock->release();
            }
        }
        return $this->backToLanguage($language);
    }

    public function requestTranslation(Language $language, int $id): RedirectResponse
    {
        return $this->setRequested($language, $id, true);
    }

    public function restoreRequestTranslation(Language $language, int $id): RedirectResponse
    {
        return $this->setRequested($language, $id, false);
    }

    private function setRequested(Language $language, int $id, bool $requested): RedirectResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        if (($lock = $this->acquireProcessLock('change translation request')) !== null) {
            try {
                $translation->update(['needs_translation' => $requested, 'approved' => !$requested]);
                Toast::flash(__('interpresso::translations.' . ($requested ? 'requested_success' : 'request_removed_success')));
            } finally {
                $lock->release();
            }
        }
        return $this->backToLanguage($language);
    }

    public function restoreTranslation(Language $language, int $id): RedirectResponse
    {
        $translation = $this->resolveTranslation($id, $language);
        if (($lock = $this->acquireProcessLock('restore translation')) !== null) {
            try {
                if ($translation->old_value !== null && !$translation->approved) {
                    $translation->update([
                        'value' => $translation->old_value, 'old_value' => null,
                        'approved_by' => $translation->previous_approved_by, 'updated_by' => $translation->previous_updated_by,
                        'previous_updated_by' => null, 'previous_approved_by' => null,
                        'approved' => true, 'exported' => true, 'updated_translation' => false,
                    ]);
                    Toast::flash(__('interpresso::translations.restored_success'));
                }
            } finally {
                $lock->release();
            }
        }
        return $this->backToLanguage($language);
    }

    public function approveAllTranslations(Language $language, BatchProcessor $processor): RedirectResponse
    {
        $this->authorizeLanguage($language);
        if (!$this->canDispatchBatch('php artisan interpresso:approve-translations --translator=' . $this->authUser()->id . ' --language=' . escapeshellarg($language->code))) {
            return $this->backToLanguage($language);
        }
        if (($lock = $this->acquireProcessLock('approve language translations')) === null) {
            return $this->backToLanguage($language);
        }
        try {
            $total = $language->translations()->where('approved', false)->count();
            if ($total > 0) {
                $batch = $processor->dispatch([new ApproveLanguagesJob($language, $this->authUser()->id)], lock: $lock, then: function () use ($total, $language): void {
                    Translator::notifyAdminApprovedTranslationsPerLanguage($total, $language);
                });
                session()->flash('batch_id', $batch->id);
            } else {
                Toast::flash(__('interpresso::translations.nothing_approved'), 'INFO');
            }
            return $this->backToLanguage($language);
        } finally {
            $lock->release();
        }
    }

    public function exportTranslationsForLanguage(Request $request, Language $language, BatchProcessor $processor): RedirectResponse
    {
        $this->authorizeLanguage($language);
        if (!$this->canDispatchBatch('php artisan interpresso:export-translations --language=' . escapeshellarg($language->code)
            . ($request->boolean('exportOnlyModels') ? ' --only-models' : ''))) {
            return $this->backToLanguage($language);
        }
        if (($lock = $this->acquireProcessLock('export language translations')) === null) {
            return $this->backToLanguage($language);
        }
        try {
            $onlyModels = $request->boolean('exportOnlyModels');
            $total = $language->translations()->isUpdated(false)->exported(false)->approved()
                ->when($onlyModels, fn ($query) => $query->type('model'))->count();
            if ($total > 0) {
                $batch = $processor->dispatch([new ExportTranslationJob($language, $onlyModels)], lock: $lock, then: function () use ($total, $language): void {
                    Translator::notifyAdminExportedTranslationsPerLanguage($total, $language);
                    resolve(ExportTranslationService::class)->exportTranslationsOnOtherHosts();
                });
                session()->flash('batch_id', $batch->id);
            } else {
                Toast::flash(__('interpresso::translations.nothing_exported'), 'INFO');
            }
            return $this->backToLanguage($language);
        } finally {
            $lock->release();
        }
    }
}
