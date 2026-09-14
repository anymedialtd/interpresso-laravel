<form method="POST" action="{{ $url }}" class="inline-flex shrink-0">
    @csrf
    @foreach($fields ?? [] as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    @include('interpresso::component.button', ['text' => $text, 'variant' => $variant ?? 'primary', 'size' => $size ?? 'md'])
</form>
