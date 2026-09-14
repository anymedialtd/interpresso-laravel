@extends('interpresso::component.table-section', ['maxWidth' => 1400])
@section('content')
@include('interpresso::component.table-h1-heading', ['title' => __('interpresso::navbar.translators')])
@if($showForm && !$showUpdatePasswordForm)
    <form id="createOrUpdateForm" method="POST" action="{{ $translator ? route('interpresso.translators.update', $translator) : route('interpresso.translators.store') }}" class="card card-body bg-base-100 shadow-md p-4 sm:p-6 gap-4">
        @csrf
        <div class="grid md:grid-cols-2 gap-4">
            @foreach(['email' => 'email', 'phone' => 'tel', 'first_name' => 'text', 'last_name' => 'text'] as $field => $inputType)
                @include('interpresso::component.input', ['name' => $field, 'label' => __('interpresso::translators.form.label.' . $field), 'type' => $inputType, 'required' => $field !== 'phone', 'value' => $translator?->{$field}])
            @endforeach
            <div id="locale" class="w-full space-y-2">
                <label for="field-locale" class="label text-sm font-medium text-base-content">{{ __('interpresso::global.interface_language') }}</label>
                <select id="field-locale" name="locale" class="select select-bordered w-full">
                    <option value="" @selected(old('locale', $translator?->locale) === null || old('locale', $translator?->locale) === '')>{{ __('interpresso::global.default_locale') }}</option>
                    @foreach($interfaceLocales as $locale => $nativeName)
                        <option value="{{ $locale }}" lang="{{ str_replace('_', '-', $locale) }}" @selected(old('locale', $translator?->locale) === $locale)>{{ $nativeName }}</option>
                    @endforeach
                </select>
                @include('interpresso::component.error', ['field' => 'locale'])
            </div>
            @if(!$translator)
                @foreach(['password', 'password_confirmation'] as $field)
                    @include('interpresso::component.input', ['name' => $field, 'label' => __('interpresso::translators.form.label.' . $field), 'type' => 'password', 'required' => true])
                @endforeach
            @endif
        </div>
        <div class="grid md:grid-cols-2 gap-4 items-center">
            @include('interpresso::component.select-checkbox-multiple', ['id' => 'translator-permissions', 'name' => 'languages', 'text' => __('interpresso::translators.form.label.languages'), 'options' => $availableLanguages->pluck('name', 'id')->all(), 'selected' => old('languages', $translator?->languages->pluck('id')->all() ?? [])])
            @include('interpresso::component.switch', ['name' => 'admin', 'label' => __('interpresso::translators.form.label.admin'), 'checked' => $translator?->admin ?? false])
        </div>
        <div class="card-actions gap-2">
            @include('interpresso::component.button', ['text' => __('interpresso::translators.form.button.' . ($translator ? 'update' : 'create')), 'size' => 'sm'])
            <a role="button" href="{{ route('interpresso.translators') }}" class="btn btn-ghost btn-sm">{{ __('interpresso::translators.form.button.close') }}</a>
            @if($translator)
                <a role="button" href="{{ route('interpresso.translators.edit', ['translator' => $translator, 'password' => 1]) }}" class="btn btn-ghost btn-sm">{{ __('interpresso::translators.form.button.update_password') }}</a>
            @endif
        </div>
    </form>
    @if($translator && \AnyMedia\Interpresso\Models\Setting::getCached()->enable_pending_notifications)
        @include('interpresso::partials.action-form', ['url' => route('interpresso.translators.notify', $translator), 'text' => __('interpresso::translators.form.button.pending_translations_notification'), 'fields' => [], 'variant' => 'primary', 'size' => 'sm'])
    @endif
@endif
@if($showUpdatePasswordForm)
    <form method="POST" action="{{ route('interpresso.translators.password', $translator) }}" class="card card-body bg-base-100 shadow-md p-4 sm:p-6 gap-4">
        @csrf
        <h2 class="text-lg font-semibold">{{ __('interpresso::translators.form.update_password_title', ['email' => $translator->email]) }}</h2>
        @foreach(['new_password', 'new_password_confirmation'] as $field)
            @include('interpresso::component.input', ['name' => $field, 'label' => __('interpresso::translators.form.label.' . substr($field, 4)), 'type' => 'password', 'required' => true])
        @endforeach
        <div class="card-actions gap-2">
            @include('interpresso::component.button', ['text' => __('interpresso::translators.form.button.update_password'), 'size' => 'sm'])
            <a role="button" href="{{ route('interpresso.translators.edit', $translator) }}" class="btn btn-ghost btn-sm">{{ __('interpresso::translators.form.button.close') }}</a>
        </div>
    </form>
@endif
<div class="card bg-base-100 shadow-md">
    <div class="flex flex-wrap items-center justify-between gap-2 p-4">
        @include('interpresso::component.search')
        <form method="GET" action="{{ route('interpresso.translators') }}">
            <input type="hidden" name="create" value="1">
            @include('interpresso::component.button', ['text' => __('interpresso::translators.button_toggle_create_form'), 'size' => 'sm'])
        </form>
        <form method="GET" action="{{ route('interpresso.translators') }}" data-filter-form>
            @include('interpresso::partials.query-fields', ['except' => ['selectedLanguages']])
            @include('interpresso::component.select-checkbox-multiple', ['id' => 'translator-language-filter', 'name' => 'selectedLanguages', 'text' => __('interpresso::translators.button_filter_languages'), 'options' => $availableLanguages->pluck('name', 'id')->all(), 'selected' => $selectedLanguages, 'apply' => true])
        </form>
    </div>
    @include('interpresso::component.table', [
        'kind' => 'translators',
        'thead' => array_map(fn ($field) => __('interpresso::translators.table.head.' . $field), ['id', 'first_name', 'last_name', 'email', 'phone', 'admin', 'languages']),
        'tbody' => ['id', 'first_name', 'last_name', 'email', 'phone', 'admin', 'languages'],
    ])
    @include('interpresso::partials.pagination')
</div>
@endsection
