@extends('interpresso::component.table-section', ['maxWidth' => 1400])
@section('content')
@include('interpresso::component.table-h1-heading', ['title' => __('interpresso::navbar.languages')])
@if(!$hasImportedLanguages && $isAdministrator)
    <p role="alert" class="alert alert-info">{{ __('interpresso::languages.info_fallback_language', ['language' => config('app.locale')]) }}</p>
@endif
@if($showForm)
    <form method="POST" action="{{ route('interpresso.languages.store') }}" class="card card-body bg-base-100 shadow-md p-4 sm:p-6 gap-4">
        @csrf
        <label for="language" class="sr-only">{{ __('interpresso::global.language') }}</label>
        <select id="language" name="language" required class="max-w-sm select select-bordered w-full">
            <option value="">{{ __('interpresso::languages.form.select.placeholder') }}</option>
            @foreach($languages as $option)<option value="{{ $option['code'] }}" @selected(old('language') === $option['code'])>{{ $option['name'] }}</option>@endforeach
        </select>
        <p class="text-sm text-base-content/70">{{ __('interpresso::languages.form.info') }}</p>
        @include('interpresso::component.error', ['field' => 'language'])
        <div class="card-actions gap-2">
            @include('interpresso::component.button', ['text' => __('interpresso::languages.form.button.add'), 'size' => 'sm'])
            <a role="button" href="{{ route('interpresso.languages') }}" class="btn btn-ghost btn-sm">{{ __('interpresso::languages.form.button.close') }}</a>
        </div>
    </form>
@endif
<div class="card bg-base-100 shadow-md">
    <div class="flex flex-wrap items-center justify-between gap-2 p-4">
        @include('interpresso::component.search')
        @if($isAdministrator)
            <div class="flex flex-wrap gap-2">
                <form method="GET" action="{{ route('interpresso.languages') }}">
                    <input type="hidden" name="create" value="1">
                    @include('interpresso::component.button', ['text' => __('interpresso::languages.button.add_language'), 'size' => 'sm'])
                </form>
                @foreach(['import-languages' => 'import_languages', 'import-translations' => 'import_translations', 'find-missing' => 'find_missing_translations', 'cancel-jobs' => 'delete_jobs'] as $action => $label)
                    @include('interpresso::partials.action-form', ['url' => route('interpresso.languages.' . $action), 'text' => __('interpresso::languages.button.' . $label), 'fields' => [], 'variant' => ($action === 'cancel-jobs' ? 'error' : 'primary'), 'size' => 'sm'])
                @endforeach
                @include('interpresso::partials.action-form', ['url' => route('interpresso.languages.approve'), 'text' => __('interpresso::translations.button.approve_all_languages'), 'fields' => [], 'variant' => 'success', 'size' => 'sm'])
                @php($onlyModels = \AnyMedia\Interpresso\Models\Setting::getCached()->db_loader)
                @include('interpresso::partials.action-form', ['url' => route('interpresso.languages.export'), 'text' => __('interpresso::translations.button.' . ($onlyModels ? 'export_all_translations_models' : 'export_all_translations')), 'fields' => ['exportOnlyModels' => (int) $onlyModels], 'variant' => 'info', 'size' => 'sm'])
            </div>
        @endif
    </div>
    @include('interpresso::component.table', [
        'kind' => 'languages',
        'thead' => [__('interpresso::languages.table.head.language_code'), __('interpresso::languages.table.head.language_name'), __('interpresso::languages.table.head.language_native_name')],
        'tbody' => ['code', 'name', 'native_name'],
    ])
    @include('interpresso::partials.pagination')
</div>
@endsection
