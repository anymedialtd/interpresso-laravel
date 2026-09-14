<div class="relative overflow-x-auto">
<table class="table table-zebra w-full">
    <thead class="bg-base-200">
        <tr>@foreach($thead as $heading)<th scope="col" class="px-4 py-3">{{ $heading }}</th>@endforeach<th scope="col" class="px-4 py-3">{{ __('interpresso::table.actions') }}</th></tr>
    </thead>
    <tbody>
        @foreach($data as $item)
            <tr class="hover:bg-base-200">
                @foreach($tbody as $field)
                    <td class="px-4 py-3 break-words">
                        @if($field === 'languages')
                            {{ $item->languages->pluck('name')->implode(', ') }}
                        @elseif(is_bool($item->{$field}))
                            @include('interpresso::component.boolean-icon', ['boolean' => $item->{$field}])
                        @else
                            {{ $item->{$field} }}
                        @endif
                    </td>
                @endforeach
                <td class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-end gap-2">
                        @if($kind === 'languages')
                            <a href="{{ route('interpresso.translations', $item) }}" class="btn btn-ghost btn-sm">{{ __('interpresso::table.view') }}</a>
                            @if($isAdministrator && \AnyMedia\Interpresso\Models\Setting::getCached()->allow_deleting_languages)
                                @include('interpresso::partials.action-form', ['url' => route('interpresso.languages.delete', $item), 'text' => __('interpresso::table.delete'), 'fields' => []])
                            @endif
                        @elseif($kind === 'translators')
                            <a href="{{ route('interpresso.translators.edit', $item) }}" class="btn btn-ghost btn-sm">{{ __('interpresso::table.edit') }}</a>
                            @if($item->id !== 1)
                                @include('interpresso::partials.action-form', ['url' => route('interpresso.translators.delete', $item), 'text' => __('interpresso::table.delete'), 'fields' => []])
                            @endif
                        @else
                            <button type="button" data-translation-url="{{ route('interpresso.translations.modal', ['language' => $language, 'id' => $item->id]) }}" class="btn btn-ghost btn-sm">{{ __('interpresso::translations.table.action.translate') }}</button>
                            @if($isAdministrator)
                                @php($parameters = ['language' => $language, 'id' => $item->id] + request()->query())
                                @if(!$item->approved && $item->value !== null && $item->value !== '')
                                    @include('interpresso::partials.action-form', ['url' => route('interpresso.translations.approve', $parameters), 'text' => __('interpresso::translations.table.action.approve'), 'fields' => []])
                                @endif
                                @include('interpresso::partials.action-form', ['url' => route('interpresso.translations.' . ($item->needs_translation ? 'restore-request' : 'request'), $parameters), 'text' => __('interpresso::translations.table.action.' . ($item->needs_translation ? 'restore_needs_translation' : 'needs_translation')), 'fields' => []])
                                @if(!$item->approved && $item->old_value !== null)
                                    @include('interpresso::partials.action-form', ['url' => route('interpresso.translations.restore', $parameters), 'text' => __('interpresso::translations.table.action.restore_translation'), 'fields' => []])
                                @endif
                            @endif
                        @endif
                    </div>
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
</div>
