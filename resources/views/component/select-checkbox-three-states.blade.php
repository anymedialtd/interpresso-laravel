<div class="dropdown dropdown-open">
    <button type="button" data-toggle="state-filter-options" aria-controls="state-filter-options" aria-expanded="false"
            class="btn btn-sm {{ count(array_filter($filters, fn ($state) => $state !== null)) ? 'btn-neutral' : 'btn-outline' }}">{{ __('interpresso::translations.checkbox_filter_button') }}</button>
    <div id="state-filter-options" hidden class="dropdown-content menu bg-base-100 rounded-box shadow-lg z-30 w-56 p-3 gap-2">
        @foreach($filters as $key => $state)
            <form method="GET" action="{{ url()->current() }}" data-state-filter>
                @include('interpresso::partials.query-fields', ['except' => [$key]])
                @if($state !== false)<input type="hidden" name="{{ $key }}" value="{{ $state === null ? 'true' : 'false' }}">@endif
                <button type="submit" aria-label="{{ __('interpresso::translations.filter.' . $key) }}" title="{{ __('interpresso::filter.' . ($state === null ? 'all' : ($state ? 'matching' : 'non_matching'))) }}"
                        class="btn btn-sm {{ $state === null ? 'btn-outline' : 'btn-neutral' }} w-full">
                    {{ __('interpresso::translations.filter.' . $key) }}
                    @if($state !== null)
                        <span aria-hidden="true">@include('interpresso::component.boolean-icon', ['boolean' => $state])</span>
                    @endif
                    <span class="sr-only">{{ __('interpresso::filter.' . ($state === null ? 'all' : ($state ? 'matching' : 'non_matching'))) }}</span>
                </button>
            </form>
        @endforeach
    </div>
</div>
