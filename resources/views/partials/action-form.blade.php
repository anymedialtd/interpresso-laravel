<form method="POST" action="{{ $url }}" class="inline-block">
    @csrf
    @foreach($fields ?? [] as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach
    @include('interpresso::component.button', ['text' => $text])
</form>
