<form method="GET" action="{{ url()->current() }}" data-search class="flex gap-2 items-center">
    @include('interpresso::partials.query-fields', ['except' => ['search', 'page', 'create', 'password']])
    <label for="search" class="sr-only">{{ __('interpresso::filter.label') }}</label>
    <input type="search" id="search" name="search" value="{{ $search ?? '' }}" placeholder="{{ __('interpresso::filter.label') }}" autocomplete="off"
           class="input input-bordered w-full">
    <button type="submit" class="btn btn-ghost btn-sm text-primary">{{ __('interpresso::filter.label') }}</button>
</form>
