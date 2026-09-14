<form method="GET" action="{{ url()->current() }}" data-search class="w-full sm:w-auto">
    @include('interpresso::partials.query-fields', ['except' => ['search', 'page', 'create', 'password']])
    <label for="search" class="sr-only">{{ __('interpresso::filter.label') }}</label>
    <div class="join w-full">
        <input type="search" id="search" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('interpresso::filter.label') }}" autocomplete="off"
               class="input input-bordered input-sm join-item min-w-0 w-full sm:w-64">
        <button type="submit" class="btn btn-ghost btn-sm join-item">{{ __('interpresso::filter.label') }}</button>
    </div>
</form>
