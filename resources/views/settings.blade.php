@extends('interpresso::component.table-section')
@section('content')
@include('interpresso::component.table-h1-heading', ['title' => __('interpresso::navbar.settings')])
<div class="card bg-base-100 shadow-md">
    <div class="card-body p-4 sm:p-6 gap-4 mx-auto w-full max-w-2xl">
        <h2 class="card-title">{{ __('interpresso::settings.import_settings') }}</h2>
        <p class="text-sm">{{ __('interpresso::settings.main_domain.label') }}: {{ config('interpresso.main_server_domain') }}</p>
        <p class="text-sm text-base-content/70">{{ __('interpresso::settings.autosave_info') }}</p>
        @php($multiHost = (bool) old('enable_multi_host', $setting->enable_multi_host))
        <form method="POST" action="{{ route('interpresso.settings.update', 'enable_multi_host') }}" data-autosave class="space-y-2">
            @csrf
            @include('interpresso::component.switch', ['id' => 'setting.enable_multi_host', 'name' => 'enable_multi_host', 'label' => __('interpresso::settings.enable_multi_host.label'), 'checked' => $setting->enable_multi_host, 'info' => __('interpresso::settings.enable_multi_host.info')])
            <button type="submit" class="btn btn-ghost btn-sm text-primary" data-save-fallback>{{ __('interpresso::global.save') }}</button>
        </form>
        <form method="POST" action="{{ route('interpresso.settings.update', 'domains') }}" data-autosave class="space-y-2">
            @csrf
            @include('interpresso::component.input', ['id' => 'setting.domains', 'name' => 'domains', 'label' => __('interpresso::settings.domains.label'), 'value' => $setting->domains, 'required' => $multiHost, 'info' => __('interpresso::settings.domains.info')])
            <button type="submit" class="btn btn-ghost btn-sm text-primary" data-save-fallback>{{ __('interpresso::global.save') }}</button>
        </form>
        @foreach([
            'db_loader' => 'db_loader_text', 'import_vendor' => 'import_vendor_text',
            'enable_pending_notifications' => 'enable_pending_translations_notifications',
            'enable_automatic_pending_notifications' => 'enable_automatic_pending_translations_notifications',
            'enable_open_ai_translations' => 'enable_open_ai_translations',
            'import_only_from_root_language' => 'import_only_from_root_language.label',
            'allow_deleting_languages' => 'allow_deleting_languages.label',
        ] as $field => $label)
            <form method="POST" action="{{ route('interpresso.settings.update', $field) }}" data-autosave class="space-y-2">
                @csrf
                @include('interpresso::component.switch', ['id' => 'setting.' . $field, 'name' => $field, 'label' => __('interpresso::settings.' . $label), 'checked' => $setting->{$field}])
                <button type="submit" class="btn btn-ghost btn-sm text-primary" data-save-fallback>{{ __('interpresso::global.save') }}</button>
            </form>
        @endforeach
    </div>
</div>
@endsection
