@extends('interpresso::component.table-section')
@section('content')
@include('interpresso::component.table-h1-heading', ['title' => __('interpresso::translations.title', ['language' => $language->name, 'code' => $language->code])])
<div class="card bg-base-100 shadow-md">
    <div class="p-4 max-w-xl space-y-2">
        <label for="translateLanguageExampleId" class="label text-sm font-medium text-base-content">{{ __('interpresso::translations.example_language.label') }}</label>
        <select id="translateLanguageExampleId" name="example_language" class="select select-bordered w-full">
            @foreach($languages as $option)<option value="{{ $option->id }}" @selected($exampleLanguageId === $option->id)>{{ $option->name }} ({{ $option->code }})</option>@endforeach
        </select>
        <p class="text-sm text-base-content/70">{{ __('interpresso::translations.example_language.info', ['language' => config('app.fallback_locale')]) }}</p>
    </div>
    <div class="flex flex-wrap gap-2 px-4 pb-4 items-center">
        @include('interpresso::component.search')
        @foreach([
            'types' => [__('interpresso::translations.filter.type'), __('interpresso::translations.filter.type_selection')],
            'updatedBy' => [__('interpresso::translations.filter.updated_by'), $translators],
            'approvedBy' => [__('interpresso::translations.filter.approved_by'), $translators],
        ] as $name => [$label, $options])
            <form method="GET" action="{{ url()->current() }}" data-filter-form>
                @include('interpresso::partials.query-fields', ['except' => [$name]])
                @include('interpresso::component.select-checkbox-multiple', ['id' => 'filter-' . $name, 'text' => $label, 'name' => $name, 'options' => $options, 'selected' => request()->input($name, []), 'apply' => true])
            </form>
        @endforeach
        @include('interpresso::component.select-checkbox-three-states')
    </div>
    @if($isAdministrator)
        <div class="flex flex-wrap items-center justify-end gap-2 px-4 pb-4">
            @php($onlyModels = \AnyMedia\Interpresso\Models\Setting::getCached()->db_loader)
            @include('interpresso::partials.action-form', ['url' => route('interpresso.translations.export', ['language' => $language] + request()->query()), 'text' => __('interpresso::translations.button.' . ($onlyModels ? 'export_translation_models' : 'export_translation')), 'fields' => ['exportOnlyModels' => (int) $onlyModels], 'variant' => 'info', 'size' => 'sm'])
            @include('interpresso::partials.action-form', ['url' => route('interpresso.translations.approve-all', ['language' => $language] + request()->query()), 'text' => __('interpresso::translations.button.approve_all', ['language_code' => $language->code]), 'fields' => [], 'variant' => 'success', 'size' => 'sm'])
        </div>
    @endif
    @include('interpresso::component.table', [
        'kind' => 'translations',
        'thead' => ['ID', ...array_map(fn ($field) => __('interpresso::translations.table.head.' . $field), ['is_vendor', 'namespace', 'group', 'needs_translation', 'approved', 'approved_by', 'updated_translation', 'updated_by', 'exported', 'key', 'content', 'old_content'])],
        'tbody' => ['id', 'is_vendor', 'namespace', 'group', 'needs_translation', 'approved', 'approver', 'updated_translation', 'updater', 'exported', 'key', 'value', 'old_value'],
    ])
    @include('interpresso::partials.pagination')
</div>
<dialog id="edit-translation-modal" aria-labelledby="translation-key" class="modal">
    <div class="modal-box w-11/12 max-w-5xl">
        <header class="flex justify-between items-center gap-2 pb-4 mb-4 border-b border-base-300">
            <h2 id="translation-key" class="font-bold"></h2>
            <button type="button" data-modal-close aria-label="{{ __('interpresso::translations.close_modal') }}" class="btn btn-ghost btn-sm">{{ __('interpresso::translations.close_modal') }}</button>
        </header>
        <p data-modal-loading role="status" hidden><span aria-hidden="true" class="loading loading-spinner loading-sm"></span> {{ __('interpresso::global.loading') }}</p>
        <p data-modal-error role="alert" hidden class="alert alert-error mb-3"></p>
        <div data-modal-content hidden>
            <p data-example class="mb-4 whitespace-pre-wrap text-base-content/70"></p>
            <form method="POST" data-translation-form>
                @csrf
                <label for="translatedValue" class="sr-only">{{ __('interpresso::global.browser.translation') }}</label>
                <textarea id="translatedValue" name="translatedValue" rows="6" class="textarea textarea-bordered w-full mb-4"></textarea>
                <div class="modal-action flex-wrap gap-2 mt-0">
                    <button type="submit" data-save class="btn btn-primary btn-sm">{{ __('interpresso::translations.action_update') }}</button>
                    <button type="submit" data-update-all hidden class="btn btn-primary btn-sm">{{ __('interpresso::translations.action_update_and_translate_others') }}</button>
                    <button type="button" data-suggest hidden class="btn btn-primary btn-sm">{{ __('interpresso::translations.action_update_with_open_ai') }}</button>
                    <span data-suggestion-loading hidden role="status">{{ __('interpresso::translations.translating') }}</span>
                </div>
            </form>
            <div data-suggestion-preview hidden class="card card-body bg-base-200 p-4 my-4">
                <p data-suggestion-text class="whitespace-pre-wrap"></p>
                <button type="button" data-use-suggestion class="btn btn-ghost btn-sm text-primary">{{ __('interpresso::translations.use_suggestion') }}</button>
            </div>
            <div data-examples class="flex flex-wrap gap-4 mt-4"></div>
        </div>
    </div>
</dialog>
@endsection
